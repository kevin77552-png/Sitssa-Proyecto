<?php

namespace App\Livewire\Admin;

use App\Models\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class MovementsReport extends Component
{
    use WithPagination;

    private const ROLES = ['administrador', 'superadministrador', 'gerente', 'almacenista'];

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $search = '';

    #[Url]
    public bool $allTime = false;

    public int $perPage = 25;

    public string $pageJump = '';

    public function mount(): void
    {
        $this->authorizeAccess();

        if ($this->from === '' && ! $this->allTime) {
            $this->from = now()->subDays(30)->toDateString();
        }
        if ($this->to === '') {
            $this->to = now()->toDateString();
        }
    }

    public function updated($name, $value): void
    {
        if ($name === 'allTime') {
            $this->from = $this->allTime ? '' : now()->subDays(30)->toDateString();
            $this->to = now()->toDateString();
        } elseif (in_array($name, ['from', 'to'], true) && $this->allTime) {
            $this->allTime = false;
        }

        if (in_array($name, ['from', 'to', 'tipo', 'search', 'allTime'], true)) {
            $this->resetPage();
        }
    }

    public function updatedPerPage($value): void
    {
        $pageSize = filter_var($value, FILTER_VALIDATE_INT);
        if ($pageSize === false || ! in_array($pageSize, [15, 20, 25, 50], true)) {
            $this->addError('perPage', 'Selecciona una cantidad de resultados válida.');
            $this->perPage = 25;

            return;
        }

        $this->resetValidation('perPage');
        $this->perPage = $pageSize;
        $this->resetPage();
    }

    public function jumpToPage(): void
    {
        $page = filter_var($this->pageJump, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1) {
            $this->addError('pageJump', 'Introduce un número de página válido.');

            return;
        }

        $lastPage = max(1, (int) ceil($this->baseQuery()->count() / $this->perPage));
        if ($page > $lastPage) {
            $this->addError('pageJump', "La página debe estar entre 1 y {$lastPage}.");

            return;
        }

        $this->resetValidation('pageJump');
        $this->pageJump = '';
        $this->setPage($page);
    }

    public function clearFilters(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
        $this->allTime = false;
        $this->tipo = '';
        $this->search = '';
        $this->resetPage();
    }

    public function exportCsv()
    {
        $this->authorizeAccess();

        if (! config('inventory_movements.enabled') || ! Schema::hasTable('inventory_movements')) {
            throw new RuntimeException('El reporte de movimientos no está disponible hasta habilitar el módulo y aplicar su migración.');
        }

        $query = $this->baseQuery()
            ->with('item')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new RuntimeException('No se pudo abrir la salida del archivo CSV.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['Fecha', 'Tipo', 'SIGICOV', 'Descripción', 'Cantidad', 'Stock después', 'Placa', 'Vehículo', 'Entregado a', 'Usuario', 'Motivo']);

                $query->chunk(500, function ($movements) use ($output): void {
                    foreach ($movements as $movement) {
                        fputcsv($output, [
                            $movement->occurred_at->format('d/m/Y H:i'),
                            $movement->tipoLabel(),
                            self::safe($movement->item?->sigicov),
                            self::safe($movement->item?->descripcion),
                            $movement->delta,
                            $movement->stock_despues,
                            self::safe($movement->vehiculo_placa),
                            self::safe($movement->vehiculo_descripcion),
                            self::safe($movement->entregado_a),
                            self::safe($movement->actor_name),
                            self::safe($movement->motivo),
                        ]);
                    }
                });
            } finally {
                fclose($output);
            }
        }, 'movimientos_inventario_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render()
    {
        $this->authorizeAccess();

        if (! config('inventory_movements.enabled')) {
            return view('livewire.admin.movements-report', [
                'available' => false,
                'unavailableMessage' => 'El registro de movimientos está deshabilitado por configuración.',
            ]);
        }

        if (! Schema::hasTable('inventory_movements')) {
            return view('livewire.admin.movements-report', [
                'available' => false,
                'unavailableMessage' => 'La tabla de movimientos aún no existe. Aplica la migración en el entorno aprobado para habilitar este reporte.',
            ]);
        }

        $unfilteredQuery = $this->baseQuery(false);
        $totals = (clone $unfilteredQuery)->selectRaw("
            coalesce(sum(case when tipo = 'entrada' then delta else 0 end), 0) as entradas,
            coalesce(sum(case when tipo = 'salida' then -delta else 0 end), 0) as salidas,
            coalesce(sum(case when tipo = 'ajuste' then delta else 0 end), 0) as ajustes
        ")->first();
        $eventCounts = (clone $unfilteredQuery)
            ->selectRaw('tipo, count(*) as aggregate')
            ->groupBy('tipo')
            ->pluck('aggregate', 'tipo');

        $byVehicle = $this->baseQuery()
            ->where('tipo', 'salida')
            ->whereNotNull('vehiculo_id')
            ->selectRaw('vehiculo_id, max(vehiculo_placa) as placa, max(vehiculo_descripcion) as descripcion, sum(-delta) as total, count(*) as veces')
            ->groupBy('vehiculo_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $rows = $this->baseQuery()
            ->with(['item', 'vehiculo'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.movements-report', [
            'available' => true,
            'rows' => $rows,
            'totals' => $totals,
            'byVehicle' => $byVehicle,
            'tipos' => InventoryMovement::TIPOS,
            'eventCounts' => $eventCounts,
        ]);
    }

    private function baseQuery(bool $withTipo = true)
    {
        $from = $this->parseDate($this->from, false);
        $to = $this->parseDate($this->to, true);
        $search = trim($this->search);

        return InventoryMovement::query()
            ->when($from, fn ($query) => $query->where('occurred_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('occurred_at', '<=', $to))
            ->when($withTipo && $this->tipo !== '', fn ($query) => $query->where('tipo', $this->tipo))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.mb_strtolower($search).'%';
                $query->whereHas('item', fn ($itemQuery) => $itemQuery
                    ->where(fn ($whereQuery) => $whereQuery
                        ->whereRaw('lower(sigicov) like ?', [$like])
                        ->orWhereRaw('lower(descripcion) like ?', [$like])));
            });
    }

    private function parseDate(string $value, bool $endOfDay): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private static function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }

    private function authorizeAccess(): void
    {
        abort_unless(in_array(Auth::user()?->role, self::ROLES, true), 403);
    }
}
