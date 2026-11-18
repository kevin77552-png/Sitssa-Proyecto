<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\SpareRequest;
use App\Models\PurchaseOrder;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\WithPagination;

class Reports extends Component
{
    use WithPagination;

    private const DETAIL_LIMIT = 10;

    public function render()
    {
        $pendingRequestCount = $this->pendingRequestsQuery()->count();
        $pendingRequests = $this->pendingRequestsQuery()
            ->with(['almacenista', 'items'])
            ->limit(self::DETAIL_LIMIT)
            ->get();

        $openOrderCount = $this->openOrdersMissingInvoiceQuery()->count();
        $openOrdersMissingInvoice = $this->openOrdersMissingInvoiceQuery()
            ->with(['provider', 'items'])
            ->limit(self::DETAIL_LIMIT)
            ->get();

        $uploadedOrderCount = $this->ordersWithInvoiceUploadedQuery()->count();

        // Rechazos frecuentes (por motivo) - from spare_requests and purchase_orders
        $rejectionsFromRequests = SpareRequest::whereNotNull('reject_reason')
            ->select('reject_reason', DB::raw('count(*) as total'))
            ->groupBy('reject_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $rejectionsFromOrders = PurchaseOrder::whereNotNull('reject_reason')
            ->select('reject_reason', DB::raw('count(*) as total'))
            ->groupBy('reject_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $avgApprovalHours = $this->averageApprovalHours();

        $criticalStock = collect();
        $lowStock = collect();
        $criticalStockCount = 0;
        $lowStockCount = 0;
        if ($this->hasStockMinimumColumns()) {
            $criticalStock = $this->criticalStockQuery()
                ->paginate(self::DETAIL_LIMIT, ['*'], 'criticalStockPage');
            $criticalStockCount = $criticalStock->total();

            if ($this->hasReorderPoint()) {
                $lowStock = $this->lowStockQuery()
                    ->paginate(self::DETAIL_LIMIT, ['*'], 'lowStockPage');
                $lowStockCount = $lowStock->total();
            }
        }

        return view('livewire.admin.reports', compact(
            'pendingRequestCount',
            'pendingRequests',
            'openOrderCount',
            'openOrdersMissingInvoice',
            'uploadedOrderCount',
            'rejectionsFromRequests',
            'rejectionsFromOrders',
            'avgApprovalHours',
            'criticalStock',
            'criticalStockCount',
            'lowStock',
            'lowStockCount',
        ));
    }

    public function export()
    {
        $pendingRequestCount = $this->pendingRequestsQuery()->count();
        $pendingRequests = $this->pendingRequestsQuery()
            ->with(['almacenista', 'items'])
            ->limit(self::DETAIL_LIMIT)
            ->get();

        $openOrderCount = $this->openOrdersMissingInvoiceQuery()->count();
        $openOrdersMissingInvoice = $this->openOrdersMissingInvoiceQuery()
            ->with(['provider', 'items'])
            ->limit(self::DETAIL_LIMIT)
            ->get();

        $uploadedOrderCount = $this->ordersWithInvoiceUploadedQuery()->count();
        $ordersWithInvoiceUploaded = $this->ordersWithInvoiceUploadedQuery()
            ->with(['provider', 'items'])
            ->limit(self::DETAIL_LIMIT)
            ->get();
        $rejectionsFromRequests = SpareRequest::whereNotNull('reject_reason')
            ->select('reject_reason', DB::raw('count(*) as total'))
            ->groupBy('reject_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
        $rejectionsFromOrders = PurchaseOrder::whereNotNull('reject_reason')
            ->select('reject_reason', DB::raw('count(*) as total'))
            ->groupBy('reject_reason')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $criticalStockQuery = $this->hasStockMinimumColumns()
            ? $this->criticalStockQuery()
            : null;
        $lowStockQuery = $this->hasReorderPoint()
            ? $this->lowStockQuery()
            : null;

        $filename = 'reporte_operativo_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use (
            $pendingRequestCount,
            $pendingRequests,
            $openOrderCount,
            $openOrdersMissingInvoice,
            $uploadedOrderCount,
            $ordersWithInvoiceUploaded,
            $rejectionsFromRequests,
            $rejectionsFromOrders,
            $criticalStockQuery,
            $lowStockQuery
        ): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo generar el reporte operativo.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, ['Reporte operativo', now()->format('d/m/Y H:i')]);
            $this->writeCsvRow($output, ['Indicador', 'Cantidad']);
            $this->writeCsvRow($output, ['Solicitudes pendientes', $pendingRequestCount]);
            $this->writeCsvRow($output, ['Órdenes sin factura', $openOrderCount]);
            $this->writeCsvRow($output, ['Órdenes con factura subida', $uploadedOrderCount]);
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Solicitudes pendientes recientes', 'Solicitado por', 'Registrada por', 'Repuesto', 'Cantidad', 'Fecha']);
            foreach ($pendingRequests as $request) {
                foreach ($request->displayItems() as $item) {
                    $this->writeCsvRow($output, [
                        '#'.$request->id,
                        $request->solicitado_por ?? '—',
                        $request->almacenista?->name ?? '—',
                        $item->descripcion,
                        $item->cantidad,
                        $request->created_at?->format('d/m/Y H:i'),
                    ]);
                }
            }
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Órdenes sin factura recientes', 'Proveedor', 'Solicitado por', 'Emitida por', 'Repuestos', 'Estado', 'Fecha']);
            foreach ($openOrdersMissingInvoice as $order) {
                $itemsDescription = $order->displayItems()
                    ->map(fn ($item) => $item->descripcion.' (x '.$item->cantidad.')'.($item->detalles ? ': '.$item->detalles : ''))
                    ->implode('; ');
                $this->writeCsvRow($output, [
                    '#'.$order->id,
                    $order->provider?->name ?? 'Sin proveedor asignado',
                    $order->solicitado_por ?? '—',
                    $order->pedido_por ?? '—',
                    $itemsDescription,
                    $order->status,
                    $order->created_at?->format('d/m/Y H:i'),
                ]);
            }
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Órdenes con factura subida', 'Proveedor', 'Solicitado por', 'Emitida por', 'Repuestos', 'Fecha de actualización']);
            foreach ($ordersWithInvoiceUploaded as $order) {
                $itemsDescription = $order->displayItems()
                    ->map(fn ($item) => $item->descripcion.' (x '.$item->cantidad.')'.($item->detalles ? ': '.$item->detalles : ''))
                    ->implode('; ');
                $this->writeCsvRow($output, [
                    '#'.$order->id,
                    $order->provider?->name ?? 'Sin proveedor asignado',
                    $order->solicitado_por ?? '—',
                    $order->pedido_por ?? '—',
                    $itemsDescription,
                    $order->updated_at?->format('d/m/Y H:i'),
                ]);
            }
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Rechazos frecuentes de solicitudes', 'Cantidad']);
            foreach ($rejectionsFromRequests as $rejection) {
                $this->writeCsvRow($output, [$rejection->reject_reason, $rejection->total]);
            }
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Rechazos frecuentes de órdenes', 'Cantidad']);
            foreach ($rejectionsFromOrders as $rejection) {
                $this->writeCsvRow($output, [$rejection->reject_reason, $rejection->total]);
            }
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, ['Disponibilidad crítica o baja', 'Disponibles', 'Stock mínimo / reposición']);
            if ($criticalStockQuery !== null) {
                foreach ($criticalStockQuery->cursor() as $item) {
                    $this->writeCsvRow($output, ['Crítico: '.$item->descripcion, $item->inventario, $item->stock_minimo]);
                }
            }
            if ($lowStockQuery !== null) {
                foreach ($lowStockQuery->cursor() as $item) {
                    $this->writeCsvRow($output, ['Bajo: '.$item->descripcion, $item->inventario, $item->reorder_point]);
                }
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pendingRequestsQuery(): Builder
    {
        return SpareRequest::query()
            ->whereIn('status', ['pending', 'por_revisar'])
            ->orderByDesc('created_at');
    }

    private function openOrdersMissingInvoiceQuery(): Builder
    {
        return PurchaseOrder::query()
            ->whereIn('status', ['aprobado', 'por_revisar', 'en_proceso'])
            ->whereNull('attachment')
            ->orderByDesc('created_at');
    }

    private function ordersWithInvoiceUploadedQuery(): Builder
    {
        return PurchaseOrder::query()
            ->where('status', 'factura_subida')
            ->orderByDesc('updated_at');
    }

    private function averageApprovalHours(): ?float
    {
        $differenceInHours = match (DB::connection()->getDriverName()) {
            'sqlite' => '(julianday(approved_at) - julianday(created_at)) * 24.0',
            'mysql', 'mariadb' => 'TIMESTAMPDIFF(MICROSECOND, created_at, approved_at) / 3600000000.0',
            'pgsql' => 'EXTRACT(EPOCH FROM (approved_at - created_at)) / 3600.0',
            'sqlsrv' => 'DATEDIFF_BIG(MICROSECOND, created_at, approved_at) / 3600000000.0',
            default => throw new \RuntimeException('El cálculo del promedio no es compatible con el driver de base de datos.'),
        };

        $average = PurchaseOrder::query()
            ->whereNotNull('approved_at')
            ->whereColumn('approved_at', '>=', 'created_at')
            ->avg(DB::raw($differenceInHours));

        return $average === null ? null : round((float) $average, 1);
    }

    private function hasStockMinimumColumns(): bool
    {
        return Schema::hasTable('inventory_items')
            && Schema::hasColumn('inventory_items', 'inventario')
            && Schema::hasColumn('inventory_items', 'stock_minimo');
    }

    private function hasReorderPoint(): bool
    {
        return $this->hasStockMinimumColumns()
            && Schema::hasColumn('inventory_items', 'reorder_point');
    }

    private function criticalStockQuery(): Builder
    {
        return InventoryItem::query()
            ->whereRaw('CAST(inventario AS INTEGER) <= CAST(stock_minimo AS INTEGER)')
            ->orderByRaw('CAST(inventario AS INTEGER) - CAST(stock_minimo AS INTEGER) ASC')
            ->orderBy('id');
    }

    private function lowStockQuery(): Builder
    {
        return InventoryItem::query()
            ->whereRaw('CAST(inventario AS INTEGER) > CAST(stock_minimo AS INTEGER)')
            ->whereRaw('CAST(inventario AS INTEGER) <= CAST(reorder_point AS INTEGER)')
            ->orderByRaw('CAST(inventario AS INTEGER) - CAST(reorder_point AS INTEGER) ASC')
            ->orderBy('id');
    }

    /**
     * @param  resource  $output
     * @param  array<int, mixed>  $values
     */
    private function writeCsvRow($output, array $values): void
    {
        $values = array_map(function ($value): string {
            $value = (string) ($value ?? '');

            return preg_match('/^\s*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
        }, $values);

        fputcsv($output, $values);
    }
}
