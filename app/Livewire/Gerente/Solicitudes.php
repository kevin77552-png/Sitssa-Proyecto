<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Log;

class Solicitudes extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $perPage = 10;
    public string $statusFilter = 'all';
    public string $search = '';
    public $rejectingId = null;
    public $rejectReason = '';
    public array $providerIds = [];

    public function mount()
    {
        $this->authorizeManager();
    }

    public function render()
    {
        $this->authorizeManager();

        abort_unless(in_array((string) $this->perPage, ['5', '10', '25', '50'], true), 422);
        abort_unless(in_array($this->statusFilter, ['all', 'pending', 'approved', 'rejected'], true), 422);

        $baseQuery = SpareRequest::query()
            ->where(function($q){
                $q->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion');
            });
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $search = trim($this->search);
        $requestId = preg_match('/^REQ-?0*(\d+)$/i', $search, $matches) === 1
            ? (int) $matches[1]
            : null;

        $requests = (clone $baseQuery)
            ->with(['almacenista', 'purchaseOrder.provider', 'items'])
            ->when($this->statusFilter === 'pending', fn ($query) => $query->where('status', 'pending'))
            ->when($this->statusFilter === 'approved', fn ($query) => $query->whereIn('status', ['approved', 'received']))
            ->when($this->statusFilter === 'rejected', fn ($query) => $query->where('status', 'rejected'))
            ->when($search !== '', function ($query) use ($search, $requestId) {
                $query->where(function ($query) use ($search, $requestId) {
                    $query->where('descripcion', 'like', '%'.$search.'%')
                        ->orWhere('detalles', 'like', '%'.$search.'%')
                        ->orWhere('solicitado_por', 'like', '%'.$search.'%')
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('descripcion', 'like', '%'.$search.'%')
                            ->orWhere('detalles', 'like', '%'.$search.'%'))
                        ->orWhereHas('almacenista', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));

                    if ($requestId !== null) {
                        $query->orWhere('id', $requestId);
                    }
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $providers = User::where('role', 'proveedor')->where('activo', true)->orderBy('name')->get();

        return view('livewire.gerente.solicitudes', [
            'requests' => $requests,
            'providers' => $providers,
            'statusCounts' => [
                'all' => (int) $statusCounts->sum(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0) + (int) ($statusCounts['received'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ],
        ]);
    }

    public function setStatusFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'pending', 'approved', 'rejected'], true), 422);

        $this->statusFilter = $filter;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        abort_unless(in_array((string) $value, ['5', '10', '25', '50'], true), 422);

        $this->perPage = (int) $value;
        $this->resetPage();
    }

    public function approve($id)
    {
        $this->authorizeManager();
        $providerId = $this->providerIds[$id] ?? null;
        $validated = Validator::make(
            ['provider_id' => $providerId],
            [
                'provider_id' => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'proveedor')
                        ->where('activo', true)),
                ],
            ]
        )->validate();

        $order = DB::transaction(function () use ($id, $validated) {
            $req = SpareRequest::with('items')
                ->where('status', 'pending')
                ->where(function ($query) {
                    $query->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion');
                })
                ->lockForUpdate()
                ->findOrFail($id);

            $order = \App\Models\PurchaseOrder::create([
                'cantidad' => $req->cantidad,
                'descripcion' => $req->descripcion,
                'solicitado_por' => $req->solicitado_por,
                'detalles' => $req->items->isEmpty() ? $req->detalles : null,
                'pedido_por' => Auth::user()->name,
                'status' => 'pendiente',
                'spare_request_id' => $req->id,
                'provider_id' => $validated['provider_id'],
            ]);
            $order->items()->createMany($req->displayItems()->map(fn ($item) => [
                'descripcion' => $item->descripcion,
                'cantidad' => $item->cantidad,
                'detalles' => $item->detalles,
            ])->all());

            Log::create([
                'accion' => 'crear_orden_desde_solicitud',
                'entidad' => 'purchase_order',
                'entidad_id' => $order->id,
                'descripcion' => 'Orden creada desde solicitud ID: '.$req->id
                    .'; Solicitada por: '.($req->solicitado_por ?: 'No especificado')
                    .'; Emitida por: '.Auth::user()->name,
                'user_id' => Auth::id(),
            ]);

            Log::create([
                'accion' => 'aprobar_solicitud',
                'entidad' => 'spare_request',
                'entidad_id' => $req->id,
                'descripcion' => 'Solicitud aprobada por gerente y vinculada a OC ID: '.$order->id,
                'user_id' => Auth::id(),
            ]);
            $req->status = 'approved';
            $req->reject_reason = null;
            $req->save();

            return $order;
        });

        unset($this->providerIds[$id]);
        session()->flash('message', 'Solicitud aprobada y orden de compra creada.');
        // refrescar paginación
        $this->resetPage();
    }

    public function openReject($id)
    {
        $this->authorizeManager();
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function cancelReject()
    {
        $this->authorizeManager();
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function submitReject()
    {
        $this->authorizeManager();
        $this->validate([
            'rejectReason' => 'required|string|max:1000',
        ]);

        $req = SpareRequest::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion');
            })
            ->find($this->rejectingId);
        if (!$req) {
            session()->flash('error', 'Solicitud no encontrada.');
            $this->cancelReject();
            return;
        }

        $req->status = 'rejected';
        $req->reject_reason = $this->rejectReason;
        $req->save();

        // Log rechazo
        Log::create([
            'accion' => 'rechazar_solicitud',
            'entidad' => 'spare_request',
            'entidad_id' => $req->id,
            'descripcion' => 'Solicitud rechazada: ' . $this->rejectReason,
            'user_id' => Auth::id(),
        ]);
        session()->flash('message', 'Solicitud rechazada.');
        $this->cancelReject();
        $this->resetPage();
    }

    private function authorizeManager(): void
    {
        abort_unless(Auth::user()?->role === 'gerente', 403);
    }
}
