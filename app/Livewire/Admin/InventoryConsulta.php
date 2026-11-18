<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryType;
use App\Models\Log;
use App\Models\SpareRequest;
use App\Support\SpreadsheetExportValue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class InventoryConsulta extends Component
{
    use WithPagination;

    private const LOW_STOCK_THRESHOLD = 10;

    public $search = '';

    public $search_descripcion = '';

    public $search_pasillo = '';

    public $search_estante = '';

    public $search_peldano = '';

    public $typeFilter = '';

    public int $perPage = 20;

    public string $pageJump = '';

    public $exportFormat = 'csv'; // allow exporting current list

    public array $selectedBarcodeItems = [];

    public bool $incompleteOnly = false;

    public bool $showExits = false;

    public ?int $barcodeItemId = null;

    public $showDeletionModal = false;

    public $deletionItemId = null;

    public $deletionReason = '';

    public $deletionItemSigicov = '';

    public $deletionItemInventory = 0;

    public ?string $deletionFeedback = null;

    public string $deletionFeedbackType = 'success';

    protected $rules = [
        'deletionReason' => 'required|string|min:5',
    ];

    /**
     * Mensajes de validación en español para la solicitud de eliminación.
     */
    protected $messages = [
        'deletionReason.required' => 'El motivo es obligatorio.',
        'deletionReason.string' => 'El motivo debe ser texto.',
        'deletionReason.min' => 'El motivo debe tener al menos :min caracteres.',
    ];

    /**
     * Nombres de atributos legibles en español.
     */
    protected $validationAttributes = [
        'deletionReason' => 'motivo',
    ];

    public function mount(): void
    {
        $this->authorizeInventoryAccess();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, [
            'search',
            'search_descripcion',
            'search_pasillo',
            'search_estante',
            'search_peldano',
            'typeFilter',
            'incompleteOnly',
        ], true)) {
            $this->resetPage();
        }

        if ($propertyName === 'deletionReason') {
            $this->validateOnly($propertyName);
        }
    }

    public function assignType(int $itemId, string $typeId): void
    {
        $this->authorizeInventoryAccess();
        abort_unless($this->isAdministrator(), 403);

        $selectedTypeId = $typeId === '' ? null : filter_var($typeId, FILTER_VALIDATE_INT);
        if ($selectedTypeId === false || ($selectedTypeId !== null && ! InventoryType::query()
            ->whereKey($selectedTypeId)
            ->where('is_active', true)
            ->exists())) {
            $this->addError("inventoryTypes.{$itemId}", 'Selecciona un tipo activo del catálogo.');

            return;
        }

        InventoryItem::query()->findOrFail($itemId)->update([
            'inventory_type_id' => $selectedTypeId,
        ]);
        $this->resetValidation("inventoryTypes.{$itemId}");
    }

    public function updatedPerPage($value): void
    {
        $pageSize = filter_var($value, FILTER_VALIDATE_INT);
        if ($pageSize === false || ! in_array($pageSize, [15, 20, 25, 50], true)) {
            $this->addError('perPage', 'Selecciona una cantidad de resultados válida.');
            $this->perPage = 20;

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

        if ($this->showExits && ! $this->unitExitsAvailable()) {
            $this->addError('pageJump', 'El registro de salidas no está disponible.');

            return;
        }

        $lastPage = max(1, (int) ceil((clone $this->currentListQuery())->count() / $this->perPage));
        if ($page > $lastPage) {
            $this->addError('pageJump', "La página debe estar entre 1 y {$lastPage}.");

            return;
        }

        $this->resetValidation('pageJump');
        $this->pageJump = '';
        $this->setPage($page);
    }

    public function render()
    {
        $this->authorizeInventoryAccess();
        $exitsAvailable = $this->unitExitsAvailable();
        $inventoryQuery = $this->inventoryQuery();
        $activeItemCount = InventoryItem::query()->count();
        $exitCount = $exitsAvailable ? $this->unitExitBaseQuery()->count() : 0;
        $monthlyTotals = collect();

        if ($this->showExits && $exitsAvailable) {
            $query = $this->unitExitQuery();
            $totalItems = (clone $query)->count();
            $items = $query->paginate($this->perPage);
        } elseif ($this->showExits) {
            $totalItems = 0;
            $items = collect();
        } else {
            $totalItems = (clone $inventoryQuery)->count();
            if ($this->isAdministrator()) {
                $monthlyTotals = (clone $inventoryQuery)->get(['fecha', 'inventario'])
                    ->groupBy(fn ($item) => $this->monthKey($item->fecha))
                    ->map(fn ($group) => $group->sum(fn ($item) => (int) $item->inventario));
            }
            $items = $inventoryQuery->paginate($this->perPage);
            if ($this->isAdministrator()) {
                foreach ($items as $item) {
                    $item->monthly_total = $monthlyTotals[$this->monthKey($item->fecha)] ?? 0;
                }
            }
        }

        $stockByType = InventoryItem::query()
            ->get(['inventory_type_id', 'inventario'])
            ->groupBy(fn (InventoryItem $item) => $item->inventory_type_id ?? 'unassigned')
            ->map(fn ($group) => $group->sum(fn (InventoryItem $item) => (int) $item->inventario));
        $inventoryTypes = InventoryType::query()->orderByDesc('is_active')->orderBy('name')->get();
        $activeInventoryTypes = $inventoryTypes->where('is_active', true)->values();
        $lowStockTypes = $activeInventoryTypes
            ->map(fn (InventoryType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'stock' => (int) $stockByType->get($type->id, 0),
            ])
            ->filter(fn (array $type) => $type['stock'] < self::LOW_STOCK_THRESHOLD)
            ->values();

        return view('livewire.admin.inventory-consulta', [
            'items' => $items,
            'barcodeItem' => $this->barcodeItemId
                ? InventoryItem::query()->find($this->barcodeItemId)
                : null,
            'totalItems' => $totalItems,
            'totalStock' => (int) $stockByType->sum(),
            'stockByType' => $stockByType,
            'activeItemCount' => $activeItemCount,
            'exitCount' => $exitCount,
            'exitsAvailable' => $exitsAvailable,
            'lowStockTypes' => $lowStockTypes,
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
            'showExits' => $this->showExits,
            'isAdministrator' => $this->isAdministrator(),
            'inventoryTypes' => $inventoryTypes,
            'activeInventoryTypes' => $activeInventoryTypes,
        ]);
    }

    public function showActiveInventory(): void
    {
        $this->authorizeInventoryAccess();
        $this->showExits = false;
        $this->resetPage();
        $this->selectedBarcodeItems = [];
        $this->barcodeItemId = null;
    }

    public function showUnitExits(): void
    {
        $this->authorizeInventoryAccess();
        $this->showExits = true;
        $this->resetPage();
        $this->selectedBarcodeItems = [];
        $this->barcodeItemId = null;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'search_descripcion', 'search_pasillo', 'search_estante', 'search_peldano', 'typeFilter', 'incompleteOnly']);
    }

    public function openDeletionModal($itemId)
    {
        $this->authorizeInventoryAccess();
        $this->deletionFeedback = null;
        $this->deletionItemId = $itemId;
        $this->deletionReason = '';
        $this->deletionItemSigicov = '';
        $this->deletionItemInventory = 0;

        $item = InventoryItem::query()->findOrFail($itemId);
        $this->deletionItemSigicov = $item->sigicov;
        $this->deletionItemInventory = max(0, (int) $item->inventario);

        $this->showDeletionModal = true;
    }

    public function openBarcodeModal(int $itemId): void
    {
        $this->authorizeInventoryAccess();
        InventoryItem::query()->findOrFail($itemId);
        $this->barcodeItemId = $itemId;
    }

    public function closeBarcodeModal(): void
    {
        $this->authorizeInventoryAccess();
        $this->barcodeItemId = null;
    }

    public function toggleCurrentPageSelection(): void
    {
        $this->authorizeInventoryAccess();
        abort_unless(! $this->showExits, 403);

        $pageIds = $this->inventoryQuery()
            ->paginate($this->perPage)
            ->getCollection()
            ->filter(fn (InventoryItem $item) => filled($item->sigicov))
            ->pluck('id')
            ->map(fn ($id) => (string) $id);
        $selectedIds = collect($this->selectedBarcodeItems)->map(fn ($id) => (string) $id);

        if ($pageIds->diff($selectedIds)->isEmpty()) {
            $this->selectedBarcodeItems = $selectedIds->diff($pageIds)->values()->all();

            return;
        }

        $newSelection = $selectedIds->merge($pageIds)->unique()->values();
        if ($newSelection->count() > 50) {
            $this->addError('selectedBarcodeItems', 'Puedes preparar hasta 50 etiquetas por vez.');

            return;
        }

        $this->resetValidation('selectedBarcodeItems');
        $this->selectedBarcodeItems = $newSelection->all();
    }

    public function printSelectedBarcodes(): void
    {
        $this->authorizeInventoryAccess();
        abort_unless(! $this->showExits, 403);

        $validated = validator(
            ['ids' => $this->selectedBarcodeItems],
            [
                'ids' => ['required', 'array', 'min:1', 'max:50'],
                'ids.*' => ['required', 'integer', 'distinct', 'exists:inventory_items,id'],
            ],
            [
                'ids.required' => 'Selecciona al menos un repuesto para imprimir sus etiquetas.',
                'ids.max' => 'Puedes preparar hasta 50 etiquetas por vez.',
            ],
        )->validate();

        $items = InventoryItem::query()
            ->whereIn('id', $validated['ids'])
            ->whereNotNull('sigicov')
            ->where('sigicov', '<>', '')
            ->pluck('id')
            ->map(fn ($id) => (string) $id);

        if ($items->count() !== count($validated['ids'])) {
            $this->addError('selectedBarcodeItems', 'Uno o más repuestos ya no tienen un SIGICOV válido.');

            return;
        }

        $this->dispatch('inventory-barcode-print-ready', url: route('inventory.barcodes.print', [
            'ids' => $items->implode(','),
        ], false));
    }

    public function export()
    {
        $this->authorizeInventoryAccess();
        $format = strtolower($this->exportFormat) === 'xlsx' ? 'Xlsx' : 'Csv';

        if ($this->showExits && ! $this->unitExitsAvailable()) {
            throw new RuntimeException('El registro de salidas no está disponible hasta habilitar el módulo y aplicar su migración.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($this->showExits) {
            $items = $this->unitExitQuery()->get();
            $headers = ['FECHA', 'SIGICOV', 'DESCRIPCIÓN', 'UNIDADES', 'PLACA', 'UNIDAD', 'ENTREGADO_A', 'REALIZADO_POR', 'MOTIVO'];
            $rows = $items->map(fn (InventoryMovement $movement) => [
                $movement->occurred_at?->format('Y-m-d H:i:s') ?? '',
                $movement->item?->sigicov,
                $movement->item?->descripcion,
                abs((int) $movement->delta),
                $movement->vehiculo_placa,
                $movement->vehiculo_descripcion,
                $movement->entregado_a,
                $movement->actor_name,
                $movement->motivo,
            ]);
        } else {
            $items = $this->inventoryQuery()
                ->whereRaw('CAST(inventario AS INTEGER) > 0')
                ->get();
            $isAdministrator = $this->isAdministrator();
            $headers = ['SIGICOV', 'DESCRIPCIÓN', 'TIPO', 'PASILLO', 'ESTANTE', 'PELDAÑO', 'FECHA', 'REALIZADO_POR', 'RESP_ACCESORIOS', 'FECHA_REGISTRO'];
            if ($isAdministrator) {
                $headers = [
                    'SIGICOV', 'DESCRIPCIÓN', 'TIPO', 'PASILLO', 'ESTANTE', 'PELDAÑO', 'FECHA',
                    'REALIZADO_POR', 'CANTIDAD_DISPONIBLE', 'RESP_ACCESORIOS', 'FECHA_REGISTRO',
                    'TOTAL_MES', 'ORIGEN', 'LOTE_IMPORTACION',
                ];
            }

            $totals = $isAdministrator
                ? $items->groupBy(fn ($item) => $this->monthKey($item->fecha))
                    ->map(fn ($group) => $group->sum(fn ($item) => (int) $item->inventario))
                : collect();

            $rows = $items->map(function ($item) use ($isAdministrator, $totals): array {
                $key = $this->monthKey($item->fecha);
                $values = [
                    $item->sigicov,
                    $item->descripcion,
                    $item->inventoryType?->name ?? 'SIN TIPO',
                    $item->pasillo,
                    $item->estante,
                    $item->peldaño,
                    $item->fecha ?: 'NO DISPONIBLE',
                    $item->realizado_por,
                ];
                if ($isAdministrator) {
                    $values[] = $item->inventario;
                }
                array_push(
                    $values,
                    $item->resp_accesorios,
                    $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : '',
                );
                if ($isAdministrator) {
                    array_push($values, $totals[$key] ?? 0, $item->origen, $item->lote_importacion);
                }

                return $values;
            });
        }

        $col = 'A';
        foreach ($headers as $h) {
            SpreadsheetExportValue::setCell($sheet, $col.'1', $h, $format === 'Csv');
            $col++;
        }

        $row = 2;
        foreach ($rows as $values) {
            $column = 'A';
            foreach ($values as $value) {
                SpreadsheetExportValue::setCell($sheet, $column.$row, $value, $format === 'Csv');
                $column++;
            }
            $row++;
        }

        $writerClass = '\\PhpOffice\\PhpSpreadsheet\\Writer\\'.$format;
        $writer = new $writerClass($spreadsheet);

        $filename = ($this->showExits ? 'salidas_unidades_export_' : 'inventario_export_')
            .date('Ymd_His').'.'.(strtolower($format) === 'xlsx' ? 'xlsx' : 'csv');

        $headers = [
            'Content-Type' => $format === 'Xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, $headers);
    }

    private function inventoryQuery(): Builder
    {
        return InventoryItem::query()
            ->with('inventoryType')
            ->when($this->isAdministrator(), fn (Builder $query) => $query->with('importBatch'))
            ->when($this->typeFilter === 'unassigned', fn (Builder $query) => $query->whereNull('inventory_type_id'))
            ->when($this->typeFilter !== '' && $this->typeFilter !== 'unassigned', fn (Builder $query) => $query->where('inventory_type_id', $this->typeFilter))
            ->when($this->search, function (Builder $query): void {
                $term = '%'.trim((string) $this->search).'%';
                $query->where(function (Builder $matches) use ($term): void {
                    $matches->whereRaw('UPPER(sigicov) LIKE UPPER(?)', [$term])
                        ->orWhereRaw('UPPER(descripcion) LIKE UPPER(?)', [$term]);
                });
            })
            ->when($this->search_descripcion, fn (Builder $query) => $query->whereRaw('UPPER(descripcion) LIKE UPPER(?)', ['%'.$this->search_descripcion.'%']))
            ->when($this->search_pasillo, fn (Builder $query) => $query->whereRaw('UPPER(pasillo) LIKE UPPER(?)', ['%'.$this->search_pasillo.'%']))
            ->when($this->search_estante, fn (Builder $query) => $query->whereRaw('UPPER(estante) LIKE UPPER(?)', ['%'.$this->search_estante.'%']))
            ->when($this->search_peldano, fn (Builder $query) => $query->whereRaw('UPPER(peldaño) LIKE UPPER(?)', ['%'.$this->search_peldano.'%']))
            ->when($this->incompleteOnly, function (Builder $query): void {
                $query->where(function (Builder $incomplete): void {
                    foreach (['pasillo', 'estante', 'peldaño'] as $field) {
                        $incomplete->orWhere(function (Builder $missing) use ($field): void {
                            $missing->whereNull($field)
                                ->orWhere($field, '')
                                ->orWhereRaw("UPPER({$field}) IN (?, ?, ?, ?)", [
                                    'NO DISPONIBLE',
                                    '0',
                                    '-',
                                    '--',
                                ]);
                        });
                    }
                });
            })
            ->orderBy('id', 'desc');
    }

    private function currentListQuery(): Builder
    {
        return $this->showExits ? $this->unitExitQuery() : $this->inventoryQuery();
    }

    private function unitExitQuery(): Builder
    {
        return $this->unitExitBaseQuery()
            ->with(['item.inventoryType'])
            ->when($this->typeFilter === 'unassigned', fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->whereNull('inventory_type_id')))
            ->when($this->typeFilter !== '' && $this->typeFilter !== 'unassigned', fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->where('inventory_type_id', $this->typeFilter)))
            ->when($this->search, function (Builder $query): void {
                $term = '%'.trim((string) $this->search).'%';
                $query->whereHas('item', function (Builder $item) use ($term): void {
                    $item->where(function (Builder $matches) use ($term): void {
                        $matches->whereRaw('UPPER(sigicov) LIKE UPPER(?)', [$term])
                            ->orWhereRaw('UPPER(descripcion) LIKE UPPER(?)', [$term]);
                    });
                });
            })
            ->when($this->search_descripcion, fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->whereRaw('UPPER(descripcion) LIKE UPPER(?)', ['%'.$this->search_descripcion.'%'])))
            ->when($this->search_pasillo, fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->whereRaw('UPPER(pasillo) LIKE UPPER(?)', ['%'.$this->search_pasillo.'%'])))
            ->when($this->search_estante, fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->whereRaw('UPPER(estante) LIKE UPPER(?)', ['%'.$this->search_estante.'%'])))
            ->when($this->search_peldano, fn (Builder $query) => $query->whereHas('item', fn (Builder $item) => $item->whereRaw('UPPER(peldaño) LIKE UPPER(?)', ['%'.$this->search_peldano.'%'])))
            ->when($this->incompleteOnly, function (Builder $query): void {
                $query->whereHas('item', function (Builder $item): void {
                    $item->where(function (Builder $incomplete): void {
                        foreach (['pasillo', 'estante', 'peldaño'] as $field) {
                            $incomplete->orWhere(function (Builder $missing) use ($field): void {
                                $missing->whereNull($field)
                                    ->orWhere($field, '')
                                    ->orWhereRaw("UPPER({$field}) IN (?, ?, ?, ?)", ['NO DISPONIBLE', '0', '-', '--']);
                            });
                        }
                    });
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    private function unitExitBaseQuery(): Builder
    {
        return InventoryMovement::query()
            ->where('tipo', 'salida')
            ->where('delta', '<', 0)
            ->whereNotNull('vehiculo_placa');
    }

    private function unitExitsAvailable(): bool
    {
        return config('inventory_movements.enabled') && Schema::hasTable('inventory_movements');
    }

    private function monthKey(?string $date): string
    {
        if (! $date) {
            return 'none';
        }

        try {
            return Carbon::parse($date)->format('Y-m');
        } catch (\Exception) {
            return 'invalid';
        }
    }

    private function isAdministrator(): bool
    {
        return in_array(Auth::user()?->role, ['administrador', 'superadministrador'], true);
    }

    public function submitDeletionRequest()
    {
        $this->authorizeInventoryAccess();
        abort_unless(Auth::user()?->role === 'almacenista', 403);
        $this->validate([
            'deletionItemId' => 'required|integer|exists:inventory_items,id',
            'deletionReason' => 'required|string|min:5',
        ]);

        $quantity = 1;
        $reason = trim((string) $this->deletionReason);
        $result = DB::transaction(function () use ($quantity, $reason): string {
            $item = InventoryItem::query()->lockForUpdate()->find($this->deletionItemId);
            if (! $item) {
                return 'missing';
            }

            if ($quantity > max(0, (int) $item->inventario)) {
                $this->deletionItemInventory = max(0, (int) $item->inventario);

                return 'insufficient';
            }

            $existing = SpareRequest::query()
                ->where('tipo', 'eliminacion')
                ->where('inventory_item_id', $item->id)
                ->where('almacenista_id', Auth::id())
                ->whereIn('status', ['pendiente', 'in_progress', 'received'])
                ->exists();
            if ($existing) {
                return 'duplicate';
            }

            $request = SpareRequest::create([
                'cantidad' => $quantity,
                'descripcion' => 'Solicitud de eliminación de repuesto: '.mb_substr($item->sigicov.' - '.$item->descripcion, 0, 150),
                'detalles' => $reason,
                'tipo' => 'eliminacion',
                'inventory_item_id' => $item->id,
                'almacenista_id' => Auth::id(),
                'status' => 'pendiente',
            ]);

            Log::create([
                'accion' => 'solicitar_eliminacion',
                'entidad' => 'spare_request',
                'entidad_id' => $request->id,
                'descripcion' => "Cantidad: {$quantity}\nMotivo: {$reason}",
                'entidad_resumen' => 'Eliminación de repuesto: '.$item->sigicov.' - '.$item->descripcion,
                'user_id' => Auth::id(),
            ]);

            return 'created';
        });

        if ($result === 'missing') {
            $this->addError('deletionItemId', 'El repuesto ya no está disponible en el inventario.');

            return;
        }

        if ($result === 'insufficient') {
            $this->addError('deletionReason', 'No hay existencias disponibles para reportar este repuesto.');

            return;
        }

        if ($result === 'duplicate') {
            $this->deletionFeedback = 'Ya existe una solicitud de eliminación pendiente para este repuesto.';
            $this->deletionFeedbackType = 'info';
            $this->showDeletionModal = false;

            return;
        }

        $this->showDeletionModal = false;
        $this->deletionItemId = null;
        $this->deletionReason = '';
        $this->deletionItemSigicov = '';
        $this->deletionItemInventory = 0;
        $this->deletionFeedback = 'Reporte enviado al gerente para revisión. El inventario no cambia hasta que se apruebe.';
        $this->deletionFeedbackType = 'success';
    }

    private function authorizeInventoryAccess(): void
    {
        abort_unless(
            in_array(Auth::user()?->role, ['administrador', 'superadministrador', 'almacenista'], true),
            403
        );
    }
}
