<?php

namespace App\Livewire\Gerente;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Log;

class PurchaseOrdersConsulta extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public int $perPage = 10;
    public string $search = '';
    public string $statusFilter = 'all';
    public array $providerIds = [];
    public ?int $assigningOrderId = null;
    public $rejectReason = '';

    public function mount(): void
    {
        $this->authorizeManager();
    }

    public function render()
    {
        $this->authorizeManager();
        $orders = $this->ordersQuery()
            ->with(['provider', 'items'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);
        $totalOrders = PurchaseOrder::count();
        $ordersWithoutInvoice = $this->ordersWithoutInvoiceQuery()->count();
        $pendingReviewOrders = PurchaseOrder::whereIn('status', ['factura_subida', 'por_revisar'])->count();
        $invoicedOrders = PurchaseOrder::where('status', 'aprobado')->count();
        $ordersWithoutProvider = PurchaseOrder::whereNull('provider_id')->count();
        $providers = User::where('role', 'proveedor')
            ->where('activo', true)
            ->orderBy('name')
            ->get();
        $assigningOrder = $this->assigningOrderId
            ? PurchaseOrder::with('items')->whereNull('provider_id')->find($this->assigningOrderId)
            : null;

        return view('livewire.gerente.purchase-orders-consulta', compact(
            'orders',
            'totalOrders',
            'ordersWithoutInvoice',
            'pendingReviewOrders',
            'invoicedOrders',
            'ordersWithoutProvider',
            'providers',
            'assigningOrder'
        ));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        abort_unless(in_array($this->statusFilter, ['all', 'sin_factura', 'por_revisar', 'facturada', 'sin_proveedor'], true), 422);
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        abort_unless(in_array($this->perPage, [10, 25, 50], true), 422);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function openAssignProvider(int $id): void
    {
        $this->authorizeManager();
        PurchaseOrder::whereNull('provider_id')->findOrFail($id);
        $this->assigningOrderId = $id;
    }

    public function assignProvider(int $id): void
    {
        $user = $this->authorizeManager();
        $field = "providerIds.{$id}";
        $validated = Validator::make(
            ['providerIds' => [$id => $this->providerIds[$id] ?? null]],
            [
                $field => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', 'proveedor')
                        ->where('activo', true)),
                ],
            ]
        )->validate();

        DB::transaction(function () use ($id, $user, $validated): void {
            $order = PurchaseOrder::whereNull('provider_id')
                ->lockForUpdate()
                ->findOrFail($id);
            $order->provider_id = $validated['providerIds'][$id];
            $order->save();

            Log::create([
                'accion' => 'asignar_proveedor',
                'entidad' => 'purchase_order',
                'entidad_id' => $order->id,
                'descripcion' => 'Proveedor asignado a la orden de compra',
                'user_id' => $user->id,
            ]);
        });

        unset($this->providerIds[$id]);
        $this->resetValidation($field);
        $this->assigningOrderId = null;
        $this->dispatch('notification', type: 'success', message: 'Proveedor asignado correctamente');
        $this->resetPage();
    }

    public function exportCsv()
    {
        $this->authorizeManager();
        $query = $this->ordersQuery()
            ->with(['provider', 'items'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo generar el reporte de órdenes.');
            }

            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['N.º', 'Solicitado por', 'Emitida por', 'Repuestos y cantidades', 'Detalles', 'Unidades totales', 'Estado', 'Proveedor', 'Factura', 'Fecha de registro', 'Fecha de aprobación', 'Motivo de rechazo']);

                $query->chunk(500, function ($orders) use ($output): void {
                    foreach ($orders as $order) {
                        $itemsDescription = $order->displayItems()
                            ->map(fn ($item) => $item->descripcion.' (x '.$item->cantidad.')'.($item->detalles ? ': '.$item->detalles : ''))
                            ->implode('; ');
                        fputcsv($output, [
                            '#'.$order->id,
                            $this->safeCsvValue($order->solicitado_por),
                            $this->safeCsvValue($order->pedido_por),
                            $this->safeCsvValue($itemsDescription),
                            $this->safeCsvValue($order->detalles),
                            $order->cantidad,
                            $this->orderStatusLabel($order),
                            $this->safeCsvValue($order->provider?->name),
                            $order->attachment ? 'Sí' : 'No',
                            $order->created_at?->format('d/m/Y H:i'),
                            $order->approved_at?->format('d/m/Y H:i'),
                            $this->safeCsvValue($order->reject_reason),
                        ]);
                    }
                });
            } finally {
                fclose($output);
            }
        }, 'ordenes_compra_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Approve a purchase order (mark as 'aprobado' and set approved_at).
     * Only allow users with role 'gerente'.
     *
     * @param int $id
     * @return void
     */
    public function approve($id)
    {
        $user = $this->authorizeManager();

        DB::transaction(function () use ($id, $user) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            abort_unless(
                $order->attachment && in_array($order->status, ['factura_subida', 'por_revisar'], true),
                409
            );

            $order->status = 'aprobado';
            $order->approved_at = now();
            $order->save();

            Log::create([
                'accion' => 'aprobar_orden',
                'entidad' => 'purchase_order',
                'entidad_id' => $order->id,
                'descripcion' => 'Orden aprobada por gerente',
                'user_id' => $user->id,
            ]);
        });

        $this->dispatch('orderApproved', $id);
        $this->dispatch('notification', type: 'success', message: 'Orden aprobada correctamente');
        $this->dispatch('refresh')->self();
    }

    public function refresh()
    {
        $this->authorizeManager();
        // noop: trigger re-render
    }

    /**
     * Mark an order as 'por_revisar' when the gerente opens the invoice modal.
     *
     * @param int $id
     * @return void
     */
    public function markAsPorRevisar($id)
    {
        $user = $this->authorizeManager();
        $marked = DB::transaction(function () use ($id, $user) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            abort_unless($order->attachment, 409);

            if ($order->status === 'factura_subida') {
                $order->status = 'por_revisar';
                $order->save();

                Log::create([
                    'accion' => 'marcar_por_revisar',
                    'entidad' => 'purchase_order',
                    'entidad_id' => $order->id,
                    'descripcion' => 'Gerente abrió la factura para revisión',
                    'user_id' => $user->id,
                ]);

                return true;
            }

            abort_unless($order->status === 'por_revisar', 409);

            return false;
        });

        if ($marked) {
            $this->dispatch('notification', type: 'info', message: 'Orden marcada como por revisar');
            $this->dispatch('refresh')->self();
        }
    }

    /**
     * Reject a purchase order with a reason.
     *
     * @param int $id
     * @param string|null $reason
     * @return void
     */
    public function reject($id, $reason = null)
    {
        $user = $this->authorizeManager();
        $validated = Validator::make(
            ['reason' => $reason ?? $this->rejectReason],
            ['reason' => 'required|string|min:5|max:1000']
        )->validate();

        DB::transaction(function () use ($id, $user, $validated) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            abort_unless(
                $order->attachment && in_array($order->status, ['factura_subida', 'por_revisar'], true),
                409
            );

            $order->status = 'rechazado';
            $order->reject_reason = $validated['reason'];
            $order->approved_at = null;
            $order->save();

            Log::create([
                'accion' => 'rechazar_orden',
                'entidad' => 'purchase_order',
                'entidad_id' => $order->id,
                'descripcion' => 'Orden rechazada: '.$validated['reason'],
                'user_id' => $user->id,
            ]);
        });

        $this->dispatch('orderRejected', $id);
        $this->dispatch('notification', type: 'success', message: 'Orden rechazada correctamente');
        $this->dispatch('refresh')->self();

        $this->rejectReason = '';
    }

    private function authorizeManager(): \App\Models\User
    {
        $user = Auth::user();
        abort_unless($user?->role === 'gerente', 403);

        return $user;
    }

    private function ordersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = PurchaseOrder::query();

        if ($this->statusFilter === 'sin_factura') {
            $query->where(function ($query): void {
                $query->whereNull('attachment')->orWhere('attachment', '');
            });
        } elseif ($this->statusFilter === 'por_revisar') {
            $query->whereIn('status', ['factura_subida', 'por_revisar']);
        } elseif ($this->statusFilter === 'facturada') {
            $query->where('status', 'aprobado');
        } elseif ($this->statusFilter === 'sin_proveedor') {
            $query->whereNull('provider_id');
        }

        $search = mb_strtolower(trim($this->search));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($search, $like): void {
                $query->whereRaw('LOWER(pedido_por) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(solicitado_por) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(detalles) LIKE ?', [$like])
                    ->orWhereHas('provider', fn ($providerQuery) => $providerQuery
                        ->whereRaw('LOWER(name) LIKE ?', [$like]));

                $numericSearch = ltrim($search, '#');
                if (ctype_digit($numericSearch)) {
                    $query->orWhere('id', (int) $numericSearch);
                }
            });
        }

        return $query;
    }

    private function ordersWithoutInvoiceQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return PurchaseOrder::where(function ($query): void {
            $query->whereNull('attachment')->orWhere('attachment', '');
        });
    }

    private function orderStatusLabel(PurchaseOrder $order): string
    {
        if (! $order->attachment) {
            return 'Sin factura';
        }

        return match ($order->status) {
            'aprobado' => 'Facturada',
            'factura_subida', 'por_revisar' => 'Por revisar',
            'rechazado' => 'Rechazada',
            'en_proceso' => 'En proceso',
            default => ucfirst(str_replace('_', ' ', $order->status)),
        };
    }

    private function safeCsvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
