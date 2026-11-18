<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\Log;
use App\Models\Vehiculo;
use App\Services\InventoryMovementRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryEgreso extends Component
{
    public const MOTIVOS = [
        'preventivo' => 'Mantenimiento preventivo',
        'correctivo' => 'Mantenimiento correctivo',
        'siniestro' => 'Siniestro o accidente',
        'otro' => 'Otro (explicar)',
    ];

    use WithPagination;

    public $item_id = '';

    public $vehiculo_id = '';

    public $entregadoA = '';

    public $motivo_tipo = '';

    public $motivo = '';

    public $success = '';

    public $error = '';

    public $search = '';

    public $search_pasillo = '';

    public $search_estante = '';

    public $search_peldano = '';

    public $typeFilter = '';

    public int $perPage = 20;

    public string $pageJump = '';

    public $showSalidaModal = false;

    public ?int $barcodeItemId = null;

    public function mount()
    {
        $this->authorizeInventoryAccess();
    }

    public function openSalidaModal($itemId)
    {
        $this->authorizeInventoryAccess();
        $this->resetSalida();
        $this->item_id = $itemId;
        $this->success = '';
        $this->error = '';
        $this->showSalidaModal = true;
    }

    public function closeSalidaModal()
    {
        $this->authorizeInventoryAccess();
        $this->showSalidaModal = false;
        $this->item_id = '';
        $this->resetSalida();
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

    public function resetFilters(): void
    {
        $this->reset(['search', 'search_pasillo', 'search_estante', 'search_peldano', 'typeFilter']);
        $this->resetPage();
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['search', 'search_pasillo', 'search_estante', 'search_peldano', 'typeFilter'], true)) {
            $this->resetPage();
        }
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

        $lastPage = max(1, (int) ceil($this->inventoryQuery()->count() / $this->perPage));
        if ($page > $lastPage) {
            $this->addError('pageJump', "La página debe estar entre 1 y {$lastPage}.");

            return;
        }

        $this->resetValidation('pageJump');
        $this->pageJump = '';
        $this->setPage($page);
    }

    public function confirmSalida()
    {
        $this->authorizeInventoryAccess();
        $this->entregadoA = trim((string) $this->entregadoA);

        $this->validate([
            'item_id' => 'required|exists:inventory_items,id',
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('estado', 'activa')],
            'entregadoA' => 'required|string|max:120',
            'motivo_tipo' => ['required', Rule::in(array_keys(self::MOTIVOS))],
            'motivo' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->motivo_tipo === 'otro')],
        ], [
            'vehiculo_id.required' => 'Selecciona la unidad de la lista.',
            'vehiculo_id.exists' => 'La unidad no existe o está inactiva.',
            'entregadoA.required' => 'Indica quién recibe el repuesto.',
            'entregadoA.max' => 'El nombre de quien recibe no puede superar los 120 caracteres.',
            'motivo_tipo.required' => 'Indica el motivo de la salida.',
            'motivo.required' => 'Explica el motivo de la salida.',
        ], [
            'entregadoA' => 'persona que recibe',
        ]);

        $recipient = trim((string) $this->entregadoA);
        $note = trim((string) $this->motivo);
        $updated = DB::transaction(function () use ($note, $recipient) {
            $vehicle = Vehiculo::activas()->lockForUpdate()->findOrFail($this->vehiculo_id);
            $item = InventoryItem::lockForUpdate()->findOrFail($this->item_id);
            $stockBefore = (int) $item->inventario;
            if ($stockBefore < 1) {
                return false;
            }

            $stockAfter = $stockBefore - 1;
            $item->inventario = (string) $stockAfter;
            $item->save();

            app(InventoryMovementRecorder::class)->record(
                item: $item,
                tipo: 'salida',
                delta: -1,
                stockBefore: $stockBefore,
                vehiculo: $vehicle,
                motivo: $note !== '' ? $note : null,
                motivoTipo: $this->motivo_tipo,
                entregadoA: $recipient,
            );

            Log::create([
                'accion' => 'salida',
                'entidad' => 'inventory_item',
                'entidad_id' => $item->id,
                'descripcion' => 'Salida de 1 unidades para vehículo: Placa '.$vehicle->placa
                    .', Descripción: '.$vehicle->descripcion
                    .' | Motivo: '.self::MOTIVOS[$this->motivo_tipo]
                    .' | Entregado a: '.$recipient
                    .($note !== '' ? ' - '.$note : ''),
                'user_id' => Auth::id(),
            ]);

            if ($stockAfter === 0) {
                $item->delete();
            }

            return true;
        });

        if ($updated) {
            $this->success = 'Entrega registrada correctamente.';
            $this->error = '';
            $this->closeSalidaModal();
        } else {
            $this->error = 'No hay existencias disponibles para registrar esta entrega.';
            $this->success = '';
        }
    }

    public function render()
    {
        $this->authorizeInventoryAccess();
        $query = $this->inventoryQuery();
        $totalItems = (clone $query)->count();
        $items = $query->orderByDesc('id')->paginate($this->perPage);
        $stockByType = InventoryItem::query()
            ->get(['inventory_type_id', 'inventario'])
            ->groupBy(fn (InventoryItem $item) => $item->inventory_type_id ?? 'unassigned')
            ->map(fn ($group) => $group->sum(fn (InventoryItem $item) => (int) $item->inventario));
        $totalStock = (int) $stockByType->sum();
        $unidades = $this->showSalidaModal
            ? Vehiculo::activas()
                ->orderBy('placa')
                ->get(['id', 'placa', 'descripcion', 'tipo', 'modelo'])
                ->map(fn (Vehiculo $vehicle) => [
                    'id' => $vehicle->id,
                    'placa' => $vehicle->placa,
                    'desc' => $vehicle->descripcion ?: 'Sin descripción',
                    'tipo' => $vehicle->tipo,
                    'modelo' => $vehicle->modelo,
                    'search' => mb_strtolower(implode(' ', array_filter([
                        $vehicle->placa,
                        $vehicle->descripcion,
                        $vehicle->tipo,
                        $vehicle->modelo,
                    ]))),
                ])
                ->values()
                ->all()
            : [];

        return view('livewire.admin.inventory-egreso', [
            'items' => $items,
            'barcodeItem' => $this->barcodeItemId
                ? InventoryItem::query()->find($this->barcodeItemId)
                : null,
            'totalItems' => $totalItems,
            'totalStock' => $totalStock,
            'stockByType' => $stockByType,
            'inventoryTypes' => InventoryType::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'showSalidaModal' => $this->showSalidaModal,
            'unidades' => $unidades,
            'motivos' => self::MOTIVOS,
        ]);
    }

    private function resetSalida(): void
    {
        $this->vehiculo_id = '';
        $this->entregadoA = '';
        $this->motivo_tipo = '';
        $this->motivo = '';
        $this->resetErrorBag();
    }

    private function inventoryQuery(): Builder
    {
        return InventoryItem::query()
            ->with('inventoryType')
            ->when($this->search, function ($query): void {
                $term = '%'.trim((string) $this->search).'%';
                $query->where(function ($matches) use ($term): void {
                    $matches->whereRaw('UPPER(sigicov) LIKE UPPER(?)', [$term])
                        ->orWhereRaw('UPPER(descripcion) LIKE UPPER(?)', [$term]);
                });
            })
            ->when($this->search_pasillo, fn ($query) => $query->whereRaw('UPPER(pasillo) LIKE UPPER(?)', ['%'.$this->search_pasillo.'%']))
            ->when($this->search_estante, fn ($query) => $query->whereRaw('UPPER(estante) LIKE UPPER(?)', ['%'.$this->search_estante.'%']))
            ->when($this->search_peldano, fn ($query) => $query->whereRaw('UPPER(peldaño) LIKE UPPER(?)', ['%'.$this->search_peldano.'%']))
            ->when($this->typeFilter === 'unassigned', fn ($query) => $query->whereNull('inventory_type_id'))
            ->when($this->typeFilter !== '' && $this->typeFilter !== 'unassigned', fn ($query) => $query->where('inventory_type_id', $this->typeFilter));
    }

    private function authorizeInventoryAccess(): void
    {
        abort_unless(
            in_array(Auth::user()?->role, ['administrador', 'superadministrador', 'almacenista'], true),
            403
        );
    }
}
