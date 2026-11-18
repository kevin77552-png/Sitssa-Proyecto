<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Services\InventoryTypeSuggester;
use App\Services\InventoryMovementRecorder;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InventoryRegister extends Component
{
    public $sigicov = '';

    public $descripcion = '';

    public $inventory_type_id = '';

    public bool $showTypeSuggestion = false;

    public $pasillo = '';

    public $estante = '';

    public $peldaño = '';

    public $fecha = '';

    public $realizado_por = '';

    public $resp_accesorios = '';

    public $search = '';

    public $search_descripcion = '';

    public $search_pasillo = '';

    public $search_estante = '';

    public $search_peldano = '';

    public function mount()
    {
        $this->realizado_por = optional(Auth::user())->name ?: '';
    }

    public function hydrate()
    {
        $this->realizado_por = optional(Auth::user())->name ?: $this->realizado_por;
    }

    public function updatedDescripcion(): void
    {
        $this->showTypeSuggestion = false;
    }

    public function suggestType(): void
    {
        $this->showTypeSuggestion = true;
    }

    public function save()
    {
        $this->validate([
            'sigicov' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (InventoryItem::activeSigicovExists((string) $value)) {
                        $fail('El SIGICOV ya está registrado en un artículo activo.');
                    }
                },
            ],
            'descripcion' => 'required',
            'inventory_type_id' => ['nullable', Rule::exists('inventory_types', 'id')->where('is_active', true)],
        ]);
        $item = InventoryItem::create([
            'sigicov' => $this->sigicov,
            'descripcion' => $this->descripcion,
            'inventory_type_id' => $this->inventory_type_id ?: null,
            'pasillo' => $this->pasillo,
            'estante' => $this->estante,
            'peldaño' => $this->peldaño,
            // Convert empty fecha to null to satisfy strict MySQL date handling
            'fecha' => $this->fecha ?: null,
            'realizado_por' => optional(Auth::user())->name ?: $this->realizado_por,
            'inventario' => 1,
            'resp_accesorios' => $this->resp_accesorios,
            'origen' => 'ingreso_manual',
        ]);
        app(InventoryMovementRecorder::class)->recordSafely(
            $item,
            'entrada',
            1,
            0,
            'Alta del repuesto',
        );
        $this->reset([
            'sigicov', 'descripcion', 'inventory_type_id', 'showTypeSuggestion', 'pasillo', 'estante', 'peldaño', 'fecha', 'realizado_por', 'resp_accesorios',
        ]);
        // restore realizado_por to current user after reset
        $this->realizado_por = optional(Auth::user())->name ?: '';
    }

    public function render()
    {
        $items = InventoryItem::query()
            ->when($this->search, fn ($q) => $q->where('sigicov', 'like', '%'.$this->search.'%'))
            ->when($this->search_descripcion, fn ($q) => $q->where('descripcion', 'like', '%'.$this->search_descripcion.'%'))
            ->when($this->search_pasillo, fn ($q) => $q->where('pasillo', 'like', '%'.$this->search_pasillo.'%'))
            ->when($this->search_estante, fn ($q) => $q->where('estante', 'like', '%'.$this->search_estante.'%'))
            ->when($this->search_peldano, fn ($q) => $q->where('peldaño', 'like', '%'.$this->search_peldano.'%'))
            ->with('inventoryType')
            ->orderBy('id', 'desc')
            ->get();

        return view('livewire.admin.inventory-register', [
            'items' => $items,
            'inventoryTypes' => InventoryType::query()->where('is_active', true)->orderBy('name')->get(),
            'suggestedInventoryType' => app(InventoryTypeSuggester::class)->suggest($this->descripcion),
        ]);
    }
}
