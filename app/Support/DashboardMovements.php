<?php

namespace App\Support;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Services\InventoryMovementRecorder;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DashboardMovements
{
    public const COLOR_IN = '#1baf7a';

    public const COLOR_OUT = '#eb6834';

    public const COLOR_REQUESTS = '#2a78d6';

    public const COLOR_ORDERS = '#6250d6';

    public function __construct(private readonly InventoryMovementRecorder $recorder) {}

    public function available(): bool
    {
        return $this->recorder->enabled();
    }

    public function weekly(int $weeks = 8): array
    {
        $weeks = max(1, $weeks);
        $start = now()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $weekIndices = [];
        $labels = [];
        $entries = array_fill(0, $weeks, 0);
        $exits = array_fill(0, $weeks, 0);

        for ($index = 0; $index < $weeks; $index++) {
            $week = $start->copy()->addWeeks($index);
            $weekIndices[$week->format('o-W')] = $index;
            $labels[] = $this->shortDate($week);
        }

        InventoryMovement::query()
            ->whereIn('tipo', ['entrada', 'salida'])
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<=', now())
            ->get(['tipo', 'delta', 'occurred_at'])
            ->each(function (InventoryMovement $movement) use ($weekIndices, &$entries, &$exits): void {
                $key = $movement->occurred_at->copy()->startOfWeek(Carbon::MONDAY)->format('o-W');
                if (! array_key_exists($key, $weekIndices)) {
                    return;
                }

                $index = $weekIndices[$key];
                if ($movement->tipo === 'entrada') {
                    $entries[$index] += $movement->delta;
                } else {
                    $exits[$index] += abs($movement->delta);
                }
            });

        return [
            'labels' => $labels,
            'entradas' => $entries,
            'salidas' => $exits,
            'series' => [
                ['name' => 'Entradas', 'values' => $entries, 'color' => self::COLOR_IN],
                ['name' => 'Salidas', 'values' => $exits, 'color' => self::COLOR_OUT],
            ],
        ];
    }

    public function summary(?array $weekly = null): array
    {
        $weekly ??= $this->weekly();
        $lastWeekIndex = count($weekly['salidas']) - 1;
        $todayExits = InventoryMovement::query()
            ->where('tipo', 'salida')
            ->where('occurred_at', '>=', now()->startOfDay())
            ->where('occurred_at', '<=', now())
            ->get(['inventory_item_id', 'delta']);
        $previousWeekExitCount = (int) ($weekly['salidas'][$lastWeekIndex - 1] ?? 0);
        $previousWeekEntryCount = (int) ($weekly['entradas'][$lastWeekIndex - 1] ?? 0);

        return [
            'salidas_hoy' => (int) $todayExits->sum(fn (InventoryMovement $movement) => abs($movement->delta)),
            'repuestos_hoy' => $todayExits->pluck('inventory_item_id')->unique()->count(),
            'salidas_semana' => (int) ($weekly['salidas'][$lastWeekIndex] ?? 0),
            'entradas_semana' => (int) ($weekly['entradas'][$lastWeekIndex] ?? 0),
            'salidas_hint' => $this->variation((int) ($weekly['salidas'][$lastWeekIndex] ?? 0), $previousWeekExitCount),
            'entradas_hint' => $this->variation((int) ($weekly['entradas'][$lastWeekIndex] ?? 0), $previousWeekEntryCount),
        ];
    }

    public function recentExits(int $limit = 5): Collection
    {
        return InventoryMovement::query()
            ->with('item')
            ->where('tipo', 'salida')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (InventoryMovement $movement): array => [
                'when' => $this->relativeWhen($movement->occurred_at),
                'descripcion' => $movement->item?->descripcion ?? '—',
                'sigicov' => $movement->item?->sigicov,
                'cantidad' => abs($movement->delta),
                'placa' => $movement->vehiculo_placa,
                'vehiculo' => $movement->vehiculo_descripcion,
            ]);
    }

    public function topItems(int $days = 30, int $limit = 5): Collection
    {
        $rows = InventoryMovement::query()
            ->where('tipo', 'salida')
            ->where('occurred_at', '>=', now()->subDays(max(1, $days)))
            ->where('occurred_at', '<=', now())
            ->selectRaw('inventory_item_id, sum(-delta) as total')
            ->groupBy('inventory_item_id')
            ->orderByDesc('total')
            ->limit(max(1, $limit))
            ->get();

        $items = InventoryItem::withTrashed()
            ->whereIn('id', $rows->pluck('inventory_item_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($row): array => [
            'label' => $items->get($row->inventory_item_id)?->descripcion ?? '—',
            'sublabel' => 'SIGICOV '.($items->get($row->inventory_item_id)?->sigicov ?? ''),
            'value' => (int) $row->total,
        ]);
    }

    public function topVehicles(int $days = 30, int $limit = 5): Collection
    {
        return InventoryMovement::query()
            ->where('tipo', 'salida')
            ->whereNotNull('vehiculo_id')
            ->where('occurred_at', '>=', now()->subDays(max(1, $days)))
            ->where('occurred_at', '<=', now())
            ->selectRaw('vehiculo_id, max(vehiculo_placa) as placa, max(vehiculo_descripcion) as descripcion, sum(-delta) as total')
            ->groupBy('vehiculo_id')
            ->orderByDesc('total')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->placa,
                'sublabel' => $row->descripcion,
                'value' => (int) $row->total,
            ]);
    }

    public function monthlyOrders(int $months = 6): array
    {
        $months = max(1, $months);
        $start = now()->startOfMonth()->subMonths($months - 1);
        $monthIndices = [];
        $labels = [];
        $requests = array_fill(0, $months, 0);
        $orders = array_fill(0, $months, 0);

        for ($index = 0; $index < $months; $index++) {
            $month = $start->copy()->addMonths($index);
            $monthIndices[$month->format('Y-m')] = $index;
            $labels[] = ucfirst(str_replace('.', '', $month->locale('es')->isoFormat('MMM YYYY')));
        }

        SpareRequest::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion'))
            ->get(['created_at'])
            ->each(function (SpareRequest $request) use ($monthIndices, &$requests): void {
                $key = $request->created_at->format('Y-m');
                if (array_key_exists($key, $monthIndices)) {
                    $requests[$monthIndices[$key]]++;
                }
            });

        PurchaseOrder::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', now())
            ->get(['created_at'])
            ->each(function (PurchaseOrder $order) use ($monthIndices, &$orders): void {
                $key = $order->created_at->format('Y-m');
                if (array_key_exists($key, $monthIndices)) {
                    $orders[$monthIndices[$key]]++;
                }
            });

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'Solicitudes', 'values' => $requests, 'color' => self::COLOR_REQUESTS],
                ['name' => 'Órdenes', 'values' => $orders, 'color' => self::COLOR_ORDERS],
            ],
        ];
    }

    private function variation(int $current, int $previous): string
    {
        if ($previous <= 0) {
            return '';
        }

        $percent = (int) round(($current - $previous) / $previous * 100);

        return ($percent >= 0 ? '+' : '').$percent.'% vs semana anterior';
    }

    private function shortDate(CarbonInterface $date): string
    {
        return rtrim($date->copy()->locale('es')->isoFormat('D MMM'), '.');
    }

    private function relativeWhen(CarbonInterface $date): string
    {
        if ($date->isToday()) {
            return 'Hoy '.$date->format('H:i');
        }
        if ($date->isYesterday()) {
            return 'Ayer '.$date->format('H:i');
        }

        return $this->shortDate($date);
    }
}
