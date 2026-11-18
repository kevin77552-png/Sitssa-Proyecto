<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\Log;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Models\User;
use App\Services\HistoryLogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;

class History extends Component
{
    use WithPagination;

    public $perPage = 15;

    public $entityFilter = '';

    public $userFilter = '';

    public $dateFrom = '';

    public $dateTo = '';

    public $actionFilter = '';

    public $search = '';

    public ?int $selectedLogId = null;

    protected $paginationTheme = 'tailwind';

    public const ACTION_LABELS = [
        'crear_unidad' => 'Unidad registrada',
        'actualizar_unidad' => 'Unidad actualizada',
        'cambiar_estado_unidad' => 'Estado de unidad actualizado',
        'importar_unidades' => 'Unidades importadas',
        'aprobar_eliminacion' => 'Eliminación aprobada',
        'aprobar_orden' => 'Orden aprobada',
        'aprobar_solicitud' => 'Solicitud aprobada',
        'ajustar_inventario_por_eliminacion' => 'Inventario ajustado',
        'aplicar_lote_importacion' => 'Aplicar lote de importación',
        'crear_orden' => 'Orden creada',
        'crear_orden_desde_solicitud' => 'Orden creada',
        'crear_solicitud' => 'Solicitud creada',
        'deshabilitar' => 'Usuario deshabilitado',
        'editar' => 'Edición',
        'habilitar' => 'Usuario habilitado',
        'importar_inventario_excel' => 'Inventario importado',
        'ingreso' => 'Ingreso',
        'marcar_por_revisar' => 'Marcar para revisión',
        'marcar_visto' => 'Orden vista',
        'rechazar_eliminacion' => 'Eliminación rechazada',
        'rechazar_orden' => 'Orden rechazada',
        'rechazar_solicitud' => 'Solicitud rechazada',
        'revertir_lote_importacion' => 'Revertir lote de importación',
        'salida' => 'Salida',
        'solicitar_eliminacion' => 'Eliminación solicitada',
        'subir_factura' => 'Factura subida',
        'confirmar_recibido' => 'Recepción confirmada',
        'comentar_solicitud' => 'Comentario de solicitud',
        'configurar' => 'Configuración',
    ];

    public function updatingEntityFilter()
    {
        $this->resetPage();
    }

    public function updatingUserFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFrom()
    {
        $this->resetPage();
    }

    public function updatingDateTo()
    {
        $this->resetPage();
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'entityFilter' => ['nullable', 'string', 'max:80'],
            'actionFilter' => ['nullable', Rule::in(array_keys(self::ACTION_LABELS))],
            'userFilter' => ['nullable', 'integer'],
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function updatedDateFrom(): void
    {
        $this->validateDateFilters();
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->validateDateFilters();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['entityFilter', 'userFilter', 'dateFrom', 'dateTo', 'actionFilter', 'search']);
        $this->resetValidation();
        $this->resetPage();
    }

    public function setQuickRange(string $range): void
    {
        $today = now()->startOfDay();

        [$from, $to] = match ($range) {
            'all' => [null, null],
            'today' => [$today, $today],
            'week' => [$today->copy()->subDays(6), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            default => abort(404),
        };

        $this->dateFrom = $from?->toDateString() ?? '';
        $this->dateTo = $to?->toDateString() ?? '';
        $this->resetValidation();
        $this->resetPage();
    }

    public function showDetails(int $logId): void
    {
        $this->authorizeHistory();
        $this->selectedLogId = Log::query()->whereKey($logId)->value('id');
        abort_if($this->selectedLogId === null, 404);
    }

    public function closeDetails(): void
    {
        $this->selectedLogId = null;
    }

    public function export()
    {
        $this->authorizeHistory();
        $this->validateDateFilters();

        $query = $this->filteredQuery()->with('user')->orderByDesc('created_at');

        $export = new class($query, self::ACTION_LABELS) implements FromQuery, WithHeadings, WithMapping
        {
            public function __construct(private Builder $logs, private array $actionLabels) {}

            public function query(): Builder
            {
                return $this->logs;
            }

            public function map($log): array
            {
                return [
                    $log->created_at?->format('d/m/Y H:i'),
                    $this->actionLabels[$log->accion] ?? ucfirst(str_replace('_', ' ', $log->accion)),
                    $log->displayEntitySummary() ?? Log::entityLabel($log->entidad),
                    $log->displayDescription(),
                    $log->displayActorName(),
                ];
            }

            public function headings(): array
            {
                return ['Fecha', 'Acción', 'Registro', 'Detalle', 'Usuario'];
            }
        };

        return Excel::download($export, 'historico_movimientos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function preparePrint(): void
    {
        $this->authorizeHistory();
        $this->validateDateFilters();

        $filters = [
            'entityFilter' => $this->entityFilter,
            'userFilter' => $this->userFilter,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'actionFilter' => $this->actionFilter,
            'search' => $this->search,
        ];

        $this->dispatch('history-print-ready', url: route('admin.history.print', $filters, false));
    }

    private function authorizeHistory(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['administrador', 'superadministrador'], true), 403);
    }

    private function filteredQuery()
    {
        $hasDateErrors = $this->getErrorBag()->has('dateFrom')
            || $this->getErrorBag()->has('dateTo');

        return HistoryLogQuery::build([
            'entityFilter' => $this->entityFilter,
            'userFilter' => $this->userFilter,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'actionFilter' => $this->actionFilter,
            'search' => $this->search,
        ], $hasDateErrors);
    }

    private function validateDateFilters(): void
    {
        $this->validate();

        if ($this->dateFrom !== '' && $this->dateTo !== '' && $this->dateFrom > $this->dateTo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dateTo' => 'La fecha "Hasta" debe ser igual o posterior a "Desde".',
            ]);
        }

        $this->resetValidation('dateTo');
    }

    public function render()
    {
        $hasDateErrors = $this->getErrorBag()->has('dateFrom')
            || $this->getErrorBag()->has('dateTo');
        $query = $this->filteredQuery()->with('user')->orderBy('created_at', 'desc');
        if ($hasDateErrors) {
            $query->whereRaw('1 = 0');
        }

        $resultCount = (clone $query)->reorder()->count();
        $logs = $query->paginate($this->perPage);

        $entityIds = $logs->getCollection()
            ->groupBy('entidad')
            ->map(fn ($records) => $records->pluck('entidad_id')->filter()->unique()->values());
        $inventoryItems = InventoryItem::withTrashed()
            ->whereIn('id', $entityIds->get('inventory_item', collect()))
            ->get()
            ->keyBy('id');
        $purchaseOrders = PurchaseOrder::whereIn('id', $entityIds->get('purchase_order', collect()))->get()->keyBy('id');
        $spareRequests = SpareRequest::with('inventoryItem')->whereIn(
            'id',
            $entityIds->get('spare_request', collect())->merge($entityIds->get('solicitud', collect()))->unique()
        )->get()->keyBy('id');
        $loggedUsers = User::whereIn(
            'id',
            $entityIds->get('user', collect())->merge($entityIds->get('users', collect()))->unique()
        )->get()->keyBy('id');

        // For filter selects
        $entities = Cache::remember(
            'admin.history.entities',
            now()->addMinutes(5),
            fn () => Log::query()->select('entidad')->distinct()->orderBy('entidad')->pluck('entidad')
        );
        $users = Cache::remember(
            'admin.history.users',
            now()->addMinutes(5),
            fn () => User::query()->select('id', 'name')->orderBy('name')->get()
        );
        $actions = self::ACTION_LABELS;
        $selectedLog = $this->selectedLogId ? Log::with('user')->find($this->selectedLogId) : null;

        return view('livewire.admin.history', compact(
            'logs',
            'resultCount',
            'entities',
            'users',
            'actions',
            'inventoryItems',
            'purchaseOrders',
            'spareRequests',
            'loggedUsers',
            'selectedLog'
        ));
    }
}
