<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PurchaseOrdersConsulta extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $perPage = 8;
    public array $providerIds = [];
    public string $search = '';
    public string $providerFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedProviderFilter(): void
    {
        if (! in_array($this->providerFilter, ['', 'unassigned', 'assigned'], true)) {
            $this->providerFilter = '';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'providerFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeOrderManagement();

        $query = PurchaseOrder::query()
            ->with('items')
            ->when($this->providerFilter === 'unassigned', fn ($query) => $query->whereNull('provider_id'))
            ->when($this->providerFilter === 'assigned', fn ($query) => $query->whereNotNull('provider_id'))
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $like = '%'.$search.'%';

                $query->where(function ($query) use ($search, $like): void {
                    $query->where('pedido_por', 'like', $like)
                        ->orWhere('solicitado_por', 'like', $like)
                        ->orWhere('descripcion', 'like', $like)
                        ->orWhere('detalles', 'like', $like)
                        ->orWhereHas('provider', fn ($providerQuery) => $providerQuery->where('name', 'like', $like));

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            });

        $totalOrders = PurchaseOrder::count();
        $unassignedOrders = PurchaseOrder::whereNull('provider_id')->count();
        $assignedOrders = $totalOrders - $unassignedOrders;
        $orders = $query->with('provider')->orderBy('created_at', 'desc')->paginate($this->perPage);
        $providers = User::where('role', 'proveedor')->where('activo', true)->orderBy('name')->get();

        return view('livewire.admin.purchase-orders-consulta', compact(
            'orders',
            'providers',
            'totalOrders',
            'unassignedOrders',
            'assignedOrders'
        ));
    }

    public function assignProvider(int $id): void
    {
        abort_unless(in_array(Auth::user()?->role, ['administrador', 'superadministrador'], true), 403);
        $field = "providerIds.{$id}";
        $providerId = $this->providerIds[$id] ?? null;
        $validated = Validator::make(
            ['providerIds' => [$id => $providerId]],
            [
                $field => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'proveedor')
                        ->where('activo', true)),
                ],
            ]
        )->validate();

        $order = PurchaseOrder::whereNull('provider_id')->findOrFail($id);
        $order->provider_id = $validated['providerIds'][$id];
        $order->save();
        unset($this->providerIds[$id]);
        $this->resetValidation($field);
        $this->resetPage();
    }

    private function authorizeOrderManagement(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['administrador', 'superadministrador'], true), 403);
    }
}
