<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'accion',
        'entidad',
        'entidad_id',
        'descripcion',
        'user_id',
        'actor_name',
        'actor_role',
        'entidad_resumen',
        'cambios',
    ];

    protected $casts = [
        'cambios' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Log $log): void {
            if (blank($log->actor_name)) {
                $actor = $log->user_id
                    ? User::query()->find($log->user_id)
                    : auth()->user();

                $log->actor_name = $actor?->name ?? ($log->user_id ? 'Usuario #'.$log->user_id : 'Sistema');
                $log->actor_role = $actor?->role;
            }

            if (blank($log->entidad_resumen)) {
                $log->entidad_resumen = static::resolveEntitySummary($log);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function displayEntitySummary(): ?string
    {
        if (filled($this->entidad_resumen)) {
            return $this->entidad_resumen;
        }

        if ($this->accion === 'solicitar_eliminacion'
            && preg_match("/inventory_item: '\\d+' => '([^']*)'/", (string) $this->descripcion, $matches)) {
            return 'Eliminación de repuesto: '.$matches[1];
        }

        return null;
    }

    public function displayActorName(): string
    {
        if (filled($this->actor_name) && ! preg_match('/^Usuario #\d+$/', $this->actor_name)) {
            return $this->actor_name;
        }

        return $this->user?->name
            ?? $this->actor_name
            ?? ($this->user_id ? 'Usuario #'.$this->user_id : 'Sistema');
    }

    public function displayActorRole(): ?string
    {
        return $this->actor_role ?? $this->user?->role;
    }

    public static function entityLabel(string $entity): string
    {
        return match ($entity) {
            'inventory_item' => 'Repuesto',
            'purchase_order' => 'Orden de compra',
            'spare_request', 'solicitud' => 'Solicitud',
            'user', 'users' => 'Usuario',
            'inventory_import' => 'Importación de inventario',
            'import_batch' => 'Lote de importación',
            'setting' => 'Configuración',
            'vehiculo' => 'Unidad',
            'unidades' => 'Unidades',
            default => ucfirst(str_replace('_', ' ', $entity)),
        };
    }

    public function displayDetails(): array
    {
        if ($importDetails = $this->importBatchDetails()) {
            $countLabel = $importDetails['operation'] === 'importados'
                ? 'Registros importados'
                : 'Registros revertidos';

            return [
                'Lote' => substr($importDetails['batch'], 0, 8),
                $countLabel => number_format($importDetails['count']),
                'Respaldo' => $importDetails['backup'],
            ];
        }

        if ($this->accion === 'solicitar_eliminacion'
            && preg_match("/inventory_item: '\\d+' => '[^']*', cantidad: '(\\d+)', motivo: '(.*)'$/s", (string) $this->descripcion, $matches)) {
            return [
                'Cantidad' => $matches[1],
                'Motivo' => $matches[2],
            ];
        }

        return ['Detalle' => $this->descripcion];
    }

    public function displaySummary(): ?string
    {
        $importDetails = $this->importBatchDetails();
        if ($importDetails === null) {
            return null;
        }

        if ($importDetails['operation'] === 'importados') {
            $itemLabel = $importDetails['count'] === 1 ? 'repuesto nuevo' : 'repuestos nuevos';

            return 'Se importaron '.number_format($importDetails['count'])." {$itemLabel} al inventario. Se generó un respaldo de seguridad antes de la importación.";
        }

        $recordLabel = $importDetails['count'] === 1 ? 'registro' : 'registros';

        return 'Se revirtieron '.number_format($importDetails['count'])." {$recordLabel} de inventario. Se generó un respaldo de seguridad antes de revertir el lote.";
    }

    public function displayDescription(): string
    {
        if (($summary = $this->displaySummary()) === null) {
            return $this->descripcion;
        }

        $details = $this->displayDetails();

        return $summary.' Lote #'.$details['Lote'].'; Respaldo: '.$details['Respaldo'].'.';
    }

    private function importBatchDetails(): ?array
    {
        if (! in_array($this->accion, ['aplicar_lote_importacion', 'revertir_lote_importacion'], true)) {
            return null;
        }

        $description = (string) $this->descripcion;
        $count = null;
        $batch = null;
        $backupPath = null;
        $operation = $this->accion === 'aplicar_lote_importacion' ? 'importados' : 'revertidos';

        if (preg_match('/^Lote:\s*([^;]+);\s*Registros '.preg_quote($operation, '/').':\s*(\d+);\s*Respaldo:\s*(.+)$/u', $description, $matches)) {
            [, $batch, $count, $backupPath] = $matches;
        } elseif ($operation === 'importados' && preg_match('/^Se importaron\s+(\d+)\s+repuestos?(?: nuevo| nuevos)? al inventario\.\s*Se generó un respaldo de seguridad antes de la importación\.\s*Lote:\s*([^;]+);\s*Respaldo:\s*(.+)$/u', $description, $matches)) {
            [, $count, $batch, $backupPath] = $matches;
        } elseif ($operation === 'revertidos' && preg_match('/^Se revirtieron\s+(\d+)\s+registros? de inventario\.\s*Se generó un respaldo de seguridad antes de revertir el lote\.\s*Lote:\s*([^;]+);\s*Respaldo:\s*(.+)$/u', $description, $matches)) {
            [, $count, $batch, $backupPath] = $matches;
        }

        if ($count === null || $batch === null || $backupPath === null) {
            return null;
        }

        $backup = basename(str_replace('\\', '/', trim($backupPath)));

        return [
            'batch' => trim($batch),
            'count' => (int) $count,
            'backup' => $backup,
            'operation' => $operation,
        ];
    }

    private static function resolveEntitySummary(Log $log): ?string
    {
        $entityId = $log->entidad_id;

        return match ($log->entidad) {
            'inventory_item' => static::inventorySummary($entityId),
            'spare_request', 'solicitud' => static::spareRequestSummary($log, $entityId),
            'purchase_order' => static::purchaseOrderSummary($entityId),
            'user', 'users' => User::query()->whereKey($entityId)->value('name'),
            default => null,
        };
    }

    private static function inventorySummary(int|string|null $entityId): ?string
    {
        $item = InventoryItem::withTrashed()->find($entityId);
        if (! $item) {
            return null;
        }

        return trim('Repuesto '.$item->sigicov.' - '.$item->descripcion, ' -');
    }

    private static function spareRequestSummary(Log $log, int|string|null $entityId): ?string
    {
        $request = SpareRequest::with('inventoryItem')->find($entityId);
        if (! $request) {
            if ($log->accion === 'solicitar_eliminacion'
                && preg_match("/inventory_item: '\\d+' => '([^']*)'/", (string) $log->descripcion, $matches)) {
                return 'Eliminación de repuesto: '.$matches[1];
            }

            return null;
        }

        if ($request->tipo === 'eliminacion' && $request->inventoryItem) {
            $item = $request->inventoryItem;

            return 'Eliminación de repuesto: '.trim($item->sigicov.' - '.$item->descripcion, ' -');
        }

        $summary = 'Solicitud #'.$request->id.' - '.$request->descripcion;

        return filled($request->solicitado_por)
            ? $summary.' - Solicitada por: '.$request->solicitado_por
            : $summary;
    }

    private static function purchaseOrderSummary(int|string|null $entityId): ?string
    {
        $order = PurchaseOrder::find($entityId);

        if (! $order) {
            return null;
        }

        $summary = 'Orden #'.$order->id.' - '.$order->descripcion;
        if (filled($order->solicitado_por)) {
            $summary .= ' - Solicitada por: '.$order->solicitado_por;
        }
        if (filled($order->pedido_por)) {
            $summary .= ' - Emitida por: '.$order->pedido_por;
        }

        return $summary;
    }
}
