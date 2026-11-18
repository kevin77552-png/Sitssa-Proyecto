<?php

namespace App\Livewire\Admin;

use App\Models\InventoryItem;
use App\Models\Log;
use App\Models\InventoryType;
use App\Services\InventoryMovementRecorder;
use App\Services\InventoryTypeSuggester;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class InventoryIngreso extends Component
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

    public $search_pasillo = '';

    public $search_estante = '';

    public $showSaved = false;

    public $savedMessage = '';

    public function mount()
    {
        $this->authorizeInventoryAccess();
        $this->realizado_por = isset(Auth::user()->name) ? mb_strtoupper(Auth::user()->name, 'UTF-8') : '';
        $this->fecha = Carbon::now()->toDateString();
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
        $this->authorizeInventoryAccess();
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
            'pasillo' => 'required',
            'estante' => 'required',
            'peldaño' => 'required',
            'resp_accesorios' => 'required',
        ],
            [
                'required' => 'El campo :attribute es obligatorio.',
            ],
            [
                'sigicov' => 'SIGICOV',
                'descripcion' => 'descripción',
                'pasillo' => 'pasillo',
                'estante' => 'estante',
                'peldaño' => 'peldaño',
                'fecha' => 'fecha',
                'realizado_por' => 'realizado por',
                'resp_accesorios' => 'responsable de accesorios',
            ]);
        // Force fecha on the server to the current date for security (ignore any client-side value)
        $nowDate = Carbon::now()->toDateString();

        $item = InventoryItem::create([
            'sigicov' => $this->sigicov,
            'descripcion' => $this->descripcion,
            'inventory_type_id' => $this->inventory_type_id ?: null,
            'pasillo' => $this->pasillo,
            'estante' => $this->estante,
            'peldaño' => $this->peldaño,
            // MySQL strict mode rejects empty string for DATE columns — convert empty strings to null
            'fecha' => $nowDate,
            'realizado_por' => Auth::user()->name ?? $this->realizado_por,
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
        Log::create([
            'accion' => 'ingreso',
            'entidad' => 'inventory_item',
            'entidad_id' => $item->id,
            'descripcion' => 'Ingreso de repuesto: '.$item->sigicov.' - '.$item->descripcion,
            'user_id' => Auth::id() ?? 0,
        ]);
        $this->reset([
            'sigicov', 'descripcion', 'inventory_type_id', 'showTypeSuggestion', 'pasillo', 'estante', 'peldaño', 'fecha', 'realizado_por', 'resp_accesorios',
        ]);
        // After reset, re-fill realizado_por and fecha with current values so the readonly fields show defaults
        $this->realizado_por = isset(Auth::user()->name) ? mb_strtoupper(Auth::user()->name, 'UTF-8') : '';
        $this->fecha = Carbon::now()->toDateString();
        // Use Livewire-bound properties to signal the frontend (Alpine) to show a transient notification
        $this->savedMessage = 'Repuesto guardado correctamente.';
        $this->showSaved = true;
    }

    public function render()
    {
        $this->authorizeInventoryAccess();
        // Mostrar solo los 4 registros más recientes en el formulario de ingreso
        $items = InventoryItem::query()
            ->when($this->search, fn ($q) => $q->where('sigicov', 'like', '%'.$this->search.'%'))
            ->when($this->search_pasillo, fn ($q) => $q->where('pasillo', 'like', '%'.$this->search_pasillo.'%'))
            ->when($this->search_estante, fn ($q) => $q->where('estante', 'like', '%'.$this->search_estante.'%'))
            ->with('inventoryType')
            ->orderBy('id', 'desc')
            ->limit(4)
            ->get();

        return view('livewire.admin.inventory-ingreso', [
            'items' => $items,
            'inventoryTypes' => InventoryType::query()->where('is_active', true)->orderBy('name')->get(),
            'suggestedInventoryType' => app(InventoryTypeSuggester::class)->suggest($this->descripcion),
        ]);
    }

    private function authorizeInventoryAccess(): void
    {
        abort_unless(
            in_array(Auth::user()?->role, ['administrador', 'superadministrador', 'almacenista'], true),
            403
        );
    }
}
