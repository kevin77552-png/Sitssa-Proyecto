<?php

namespace App\Livewire\Admin;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use App\Models\Log;

class PurchaseOrders extends Component
{
    public $cantidad = 1;
    public $descripcion = '';
    public $pedido_por = '';
    public $detalles = '';
    public $providerId = '';

    public array $items = [
        ['descripcion' => '', 'cantidad' => 1],
    ];

    public $orders;

    public function mount()
    {
        $this->authorizeOrderManagement();
        $this->loadOrders();
        if (Auth::check()) {
            $this->pedido_por = Auth::user()->name;
        }
    }

    public function loadOrders()
    {
        $this->authorizeOrderManagement();
        // Mostrar solo las 2 órdenes más recientes en la vista de creación
        $this->orders = PurchaseOrder::with(['provider', 'items'])->orderBy('created_at', 'desc')->take(2)->get();
    }

    public function addItem(): void
    {
        $this->authorizeOrderManagement();
        if (count($this->items) >= 20) {
            $this->addError('items', 'Una orden puede incluir hasta 20 repuestos.');

            return;
        }

        $this->resetValidation('items');
        $this->items[] = ['descripcion' => '', 'cantidad' => 1];
    }

    public function removeItem(int $index): void
    {
        $this->authorizeOrderManagement();
        abort_unless(array_key_exists($index, $this->items), 404);

        if (count($this->items) === 1) {
            $this->items[0] = ['descripcion' => '', 'cantidad' => 1];
        } else {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }

        $this->resetValidation();
    }

    public function createOrder()
    {
        $this->authorizeOrderManagement();
        $this->useLegacyItemInputWhenNeeded();
        $validated = $this->validate([
            'items' => 'required|array|min:1|max:20',
            'items.*.descripcion' => 'required|string|max:1000',
            'items.*.cantidad' => 'required|integer|min:1|max:2147483647',
            'pedido_por' => 'nullable|string|max:255',
            'detalles' => 'nullable|string|max:2000',
            'providerId' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'proveedor')
                    ->where('activo', true)),
            ],
        ], [
            'items.*.descripcion.required' => 'Escribe el nombre o descripción del repuesto.',
            'items.*.cantidad.required' => 'Indica la cantidad de este repuesto.',
            'items.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        ]);

        $items = collect($validated['items'])
            ->map(fn (array $item): array => [
                'descripcion' => trim($item['descripcion']),
                'cantidad' => (int) $item['cantidad'],
            ])
            ->all();
        $orderDescription = collect($items)
            ->map(fn (array $item): string => $item['descripcion'])
            ->implode(', ');
        $totalQuantity = collect($items)->sum('cantidad');
        if ($totalQuantity > 2147483647) {
            $this->addError('items', 'La suma de las cantidades no puede superar 2,147,483,647.');

            return;
        }

        $order = DB::transaction(function () use ($validated, $items, $orderDescription, $totalQuantity): PurchaseOrder {
            $order = PurchaseOrder::create([
                'cantidad' => $totalQuantity,
                'descripcion' => $orderDescription,
                'pedido_por' => Auth::user()?->name,
                'detalles' => $validated['detalles'] ?? null,
                'status' => 'pendiente',
                'provider_id' => $validated['providerId'],
            ]);

            $order->items()->createMany($items);

            return $order;
        });

        $this->reset(['cantidad', 'descripcion', 'detalles', 'providerId']);
        $this->items = [['descripcion' => '', 'cantidad' => 1]];
        $this->loadOrders();

    // Emitir evento para el frontend usando el helper `dispatch` disponible en este proyecto
    $this->dispatch('order-created');

        // Log creación de orden
        Log::create([
            'accion' => 'crear_orden',
            'entidad' => 'purchase_order',
            'entidad_id' => $order->id,
            'descripcion' => 'Orden creada manualmente: '.$order->descripcion
                .'; Emitida por: '.($order->pedido_por ?: 'No especificado'),
            'user_id' => Auth::id(),
        ]);

        session()->flash('success', "Pedido #{$order->id} creado correctamente.");
    }

    public function resetForm(): void
    {
        $this->authorizeOrderManagement();
        $this->reset(['cantidad', 'descripcion', 'detalles', 'providerId']);
        $this->items = [['descripcion' => '', 'cantidad' => 1]];
        $this->resetValidation();
    }

    public function markAsSeen($id)
    {
        $this->authorizeOrderManagement();
        $order = PurchaseOrder::where('status', 'pendiente')->findOrFail($id);

        $order->update(['status' => 'en_proceso']);

        $this->loadOrders();
    }

    public function render()
    {
        $this->authorizeOrderManagement();

        return view('livewire.admin.purchase-orders', [
            'providers' => User::where('role', 'proveedor')->where('activo', true)->orderBy('name')->get(),
        ]);
    }

    private function authorizeOrderManagement(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['administrador', 'superadministrador', 'gerente'], true), 403);
    }

    private function useLegacyItemInputWhenNeeded(): void
    {
        $firstItem = $this->items[0] ?? null;
        if (
            count($this->items) <= 1
            && is_array($firstItem)
            && blank($firstItem['descripcion'] ?? null)
            && filled($this->descripcion)
        ) {
            $this->items = [[
                'descripcion' => $this->descripcion,
                'cantidad' => $this->cantidad,
            ]];
        }
    }
}
