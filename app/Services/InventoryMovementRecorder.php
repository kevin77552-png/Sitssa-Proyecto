<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Vehiculo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InventoryMovementRecorder
{
    private static ?bool $tableExists = null;

    public static function resetCache(): void
    {
        self::$tableExists = null;
    }

    public function enabled(): bool
    {
        if (! config('inventory_movements.enabled')) {
            return false;
        }

        return self::$tableExists ??= Schema::hasTable('inventory_movements');
    }

    public function record(
        InventoryItem $item,
        string $tipo,
        int $delta,
        int $stockBefore,
        ?Vehiculo $vehiculo = null,
        ?string $motivo = null,
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        ?CarbonInterface $at = null,
        ?string $motivoTipo = null,
        ?string $entregadoA = null,
    ): ?InventoryMovement {
        if (! $this->enabled() || $delta === 0) {
            return null;
        }

        $actor = Auth::user();

        return InventoryMovement::create([
            'inventory_item_id' => $item->getKey(),
            'tipo' => $tipo,
            'delta' => $delta,
            'stock_antes' => $stockBefore,
            'stock_despues' => $stockBefore + $delta,
            'vehiculo_id' => $vehiculo?->getKey(),
            'vehiculo_placa' => $vehiculo?->placa,
            'vehiculo_descripcion' => $vehiculo?->descripcion,
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
            'motivo_tipo' => $motivoTipo,
            'entregado_a' => $entregadoA !== null ? mb_substr($entregadoA, 0, 120) : null,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'occurred_at' => $at ?? now(),
        ]);
    }

    public function recordSafely(
        InventoryItem $item,
        string $tipo,
        int $delta,
        int $stockBefore,
        ?string $motivo = null,
    ): ?InventoryMovement {
        try {
            return $this->record($item, $tipo, $delta, $stockBefore, motivo: $motivo);
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar el movimiento de inventario', [
                'inventory_item_id' => $item->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function findOrCreateVehiculo(string $placa, ?string $descripcion = null): Vehiculo
    {
        $normalizedPlate = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $placa)));
        $description = $descripcion !== null ? trim($descripcion) : null;

        $vehicle = Vehiculo::query()->whereRaw('upper(placa) = ?', [$normalizedPlate])->first();

        if ($vehicle !== null) {
            if (blank($vehicle->descripcion) && filled($description)) {
                $vehicle->update(['descripcion' => $description]);
            }

            return $vehicle;
        }

        return Vehiculo::create([
            'placa' => $normalizedPlate,
            'descripcion' => filled($description) ? $description : null,
        ]);
    }
}
