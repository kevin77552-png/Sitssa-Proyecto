<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillInventoryMovements extends Command
{
    protected $signature = 'inventory:backfill-movements
                            {--dry-run : Muestra lo que haría sin escribir}
                            {--verify : Solo compara el stock de cada repuesto con la suma de sus movimientos}';

    protected $description = 'Crea saldos iniciales de movimientos para repuestos sin movimientos o verifica consistencia';

    public function handle(): int
    {
        if (! config('inventory_movements.enabled')) {
            $this->warn('El registro de movimientos está deshabilitado.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('inventory_movements')) {
            $this->error('No existe la tabla inventory_movements. Ejecuta las migraciones en una base de pruebas o copia primero.');

            return self::FAILURE;
        }

        return $this->option('verify') ? $this->verify() : $this->backfill();
    }

    private function backfill(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        InventoryItem::withTrashed()
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('inventory_movements')
                ->whereColumn('inventory_movements.inventory_item_id', 'inventory_items.id'))
            ->chunkById(500, function ($items) use ($dryRun, &$created, &$skipped): void {
                DB::transaction(function () use ($items, $dryRun, &$created, &$skipped): void {
                    foreach ($items as $item) {
                        $lockedItem = InventoryItem::withTrashed()->lockForUpdate()->find($item->id);
                        if ($lockedItem === null) {
                            continue;
                        }

                        if (InventoryMovement::query()->where('inventory_item_id', $lockedItem->id)->exists()) {
                            continue;
                        }

                        $stock = (int) $lockedItem->inventario;
                        if ($stock <= 0) {
                            $skipped++;

                            continue;
                        }

                        $created++;
                        if ($dryRun) {
                            continue;
                        }

                        InventoryMovement::create([
                            'inventory_item_id' => $lockedItem->id,
                            'tipo' => $lockedItem->origen === 'inventario_inicial' ? 'inventario_inicial' : 'saldo_inicial',
                            'delta' => $stock,
                            'stock_antes' => 0,
                            'stock_despues' => $stock,
                            'actor_name' => 'Sistema',
                            'motivo' => 'Saldo al activar el registro de movimientos',
                            'referencia_tipo' => $lockedItem->import_batch_id ? 'import_batch' : null,
                            'referencia_id' => $lockedItem->import_batch_id,
                            'occurred_at' => $lockedItem->created_at ?? now(),
                        ]);
                    }
                });
            });

        $prefix = $dryRun ? '[simulación] ' : '';
        $this->info("{$prefix}Saldos creados: {$created}. Repuestos sin stock (omitidos): {$skipped}.");

        return self::SUCCESS;
    }

    private function verify(): int
    {
        $mismatches = [];

        InventoryItem::withTrashed()->chunkById(500, function ($items) use (&$mismatches): void {
            $ids = $items->modelKeys();
            $sums = InventoryMovement::query()
                ->whereIn('inventory_item_id', $ids)
                ->selectRaw('inventory_item_id, sum(delta) as total')
                ->groupBy('inventory_item_id')
                ->pluck('total', 'inventory_item_id');

            foreach ($items as $item) {
                $stock = (int) $item->inventario;
                $sum = (int) ($sums[$item->id] ?? 0);
                if ($stock !== $sum) {
                    $mismatches[] = [$item->id, $item->sigicov, $stock, $sum];
                }
            }
        });

        if ($mismatches === []) {
            $this->info('Consistente: el stock de todos los repuestos coincide con la suma de sus movimientos.');

            return self::SUCCESS;
        }

        $this->error(count($mismatches).' repuestos con diferencia entre su stock y sus movimientos:');
        $this->table(['id', 'SIGICOV', 'stock actual', 'suma de movimientos'], array_slice($mismatches, 0, 50));

        return self::FAILURE;
    }
}
