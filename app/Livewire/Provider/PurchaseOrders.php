<?php

namespace App\Livewire\Provider;

use App\Models\PurchaseOrder;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log as LogFacade;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PurchaseOrders extends Component
{
    use WithFileUploads;

    public string $search = '';
    public string $statusFilter = 'all';

    // attachments is an associative array: attachments[orderId] => UploadedFile
    public $attachments = [];
    // id del pedido actualmente abierto en el modal de subida
    public $uploadingOrderId = null;

    public function mount()
    {
        $this->authorizeProvider();
    }

    public function loadOrders()
    {
        $this->authorizeProvider();
    }

    public function markAsSeen($id)
    {
        $this->authorizeProvider();
        DB::transaction(function () use ($id) {
            $order = PurchaseOrder::where('provider_id', Auth::id())
                ->lockForUpdate()
                ->findOrFail($id);

            if ($order->status !== 'pendiente') {
                return;
            }

            $order->update(['status' => 'en_proceso']);

            Log::create([
                'accion' => 'marcar_visto',
                'entidad' => 'purchase_order',
                'entidad_id' => $order->id,
                'descripcion' => 'Proveedor marcó la orden como vista',
                'user_id' => Auth::id(),
            ]);
        });
    }

    /**
     * Upload an invoice/attachment for a purchase order (provider uploads).
     */
    public function uploadInvoice($id)
    {
        $this->authorizeProvider();
        if (! isset($this->attachments[$id])) {
            session()->flash('error', 'Seleccione un archivo antes de subir.');
            return;
        }

        $file = $this->attachments[$id];

        // Validación: pdf, jpg, jpeg, png, máximo 3 MB (3072 KB)
        $this->validate([
            "attachments.$id" => 'file|mimes:pdf,jpg,jpeg,png|max:3072',
        ], [
            "attachments.$id.max" => 'El archivo no puede superar los 3 MB.',
            "attachments.$id.mimes" => 'Formato no permitido. Use pdf, jpg, jpeg o png.',
        ]);

        $order = PurchaseOrder::where('provider_id', Auth::id())->findOrFail($id);
        abort_unless(in_array($order->status, ['pendiente', 'en_proceso', 'rechazado'], true), 409);
        abort_if($order->attachment && $order->status !== 'rechazado', 409);

        $disk = Storage::disk('local');
        $path = $file->store('invoices', 'local');
        if (! is_string($path)) {
            session()->flash('error', 'No se pudo guardar la factura. Inténtelo de nuevo o contacte al administrador.');
            return;
        }

        if (! Schema::hasColumn('purchase_orders', 'attachment')) {
            $disk->delete($path);
            session()->flash('error', 'La columna "attachment" no existe en la base de datos. Ejecute: php artisan migrate');
            return;
        }

        $previousPath = $order->attachment;
        try {
            DB::transaction(function () use ($order, $path) {
                $order->update([
                    'attachment' => $path,
                    'status' => 'factura_subida',
                    'approved_at' => null,
                ]);

                Log::create([
                    'accion' => 'subir_factura',
                    'entidad' => 'purchase_order',
                    'entidad_id' => $order->id,
                    'descripcion' => 'Proveedor subió/reesubió factura: ' . $path,
                    'user_id' => Auth::id(),
                ]);
            });
        } catch (QueryException $e) {
            if (! $disk->delete($path)) {
                LogFacade::warning('Unable to remove invoice after metadata save failure.', [
                    'purchase_order_id' => $order->id,
                    'path' => $path,
                ]);
            }

            LogFacade::error('Unable to save invoice metadata.', [
                'purchase_order_id' => $order->id,
                'exception' => $e,
            ]);
            session()->flash('error', 'No se pudo guardar la factura. Inténtelo de nuevo o contacte al administrador.');
            return;
        }

        if ($previousPath && $previousPath !== $path && $disk->exists($previousPath)) {
            try {
                $backupDir = 'invoices/replaced/' . $order->id;
                $backupPath = $backupDir . '/' . Str::uuid() . '_' . basename($previousPath);
                if (! $disk->copy($previousPath, $backupPath)) {
                    throw new \RuntimeException('Unable to copy the previous invoice.');
                }
                if (! $disk->delete($previousPath)) {
                    LogFacade::warning('Unable to remove archived invoice source.', [
                        'purchase_order_id' => $order->id,
                        'path' => $previousPath,
                    ]);
                }
            } catch (\Throwable $e) {
                LogFacade::warning('Unable to archive replaced invoice.', [
                    'purchase_order_id' => $order->id,
                    'exception' => $e,
                ]);
            }
        }

        // Limpiar input para ese pedido
        unset($this->attachments[$id]);

        // Cerrar modal
        $this->uploadingOrderId = null;

        // Si no hubo error previo, indicar éxito
        if (! session()->get('error')) {
            session()->flash('success', 'Factura subida correctamente.');
        }

        $this->loadOrders();
    }

    public function openUploadModal($id)
    {
        $this->authorizeProvider();
        PurchaseOrder::where('provider_id', Auth::id())->findOrFail($id);
        $this->uploadingOrderId = $id;
    }

    public function closeUploadModal()
    {
        $this->authorizeProvider();
        $this->uploadingOrderId = null;
    }

    public function clearFilters(): void
    {
        $this->authorizeProvider();
        $this->search = '';
        $this->statusFilter = 'all';
    }

    public function render()
    {
        $this->authorizeProvider();
        $allOrders = PurchaseOrder::with('items')->where('provider_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
        $statusCounts = [
            'all' => $allOrders->count(),
            'pendiente' => $allOrders->where('status', 'pendiente')->count(),
            'en_proceso' => $allOrders->where('status', 'en_proceso')->count(),
            'factura_subida' => $allOrders->where('status', 'factura_subida')->count(),
            'rechazado' => $allOrders->where('status', 'rechazado')->count(),
        ];
        $search = Str::lower(trim($this->search));
        $orders = $allOrders->filter(function (PurchaseOrder $order) use ($search) {
            $matchesStatus = $this->statusFilter === 'all' || $this->statusFilter === $order->status;
            $searchable = implode(' ', array_filter([
                $order->id,
                $order->pedido_por,
                $order->solicitado_por,
                $order->descripcion,
                $order->detalles,
            ]));

            return $matchesStatus && ($search === '' || Str::contains(Str::lower($searchable), $search));
        })->values();

        $modalOrder = $this->uploadingOrderId
            ? PurchaseOrder::with('items')->where('provider_id', Auth::id())->find($this->uploadingOrderId)
            : null;

        return view('livewire.provider.purchase-orders', compact('orders', 'modalOrder', 'statusCounts'));
    }

    private function authorizeProvider(): void
    {
        abort_unless(Auth::user()?->role === 'proveedor', 403);
    }
}
