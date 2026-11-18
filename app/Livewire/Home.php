<?php

namespace App\Livewire;

use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Support\DashboardMovements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Home extends Component
{
    private const QUICK_ACTIONS = [
        'administrador' => [
            ['module' => 'admin.inventory.consulta', 'label' => 'Consultar inventario'],
            ['module' => 'admin.purchase_orders', 'label' => 'Crear pedido'],
            ['module' => 'admin.users.register', 'label' => 'Registrar usuario'],
            ['module' => 'admin.history', 'label' => 'Ver histórico'],
        ],
        'superadministrador' => [
            ['module' => 'admin.inventory.consulta', 'label' => 'Consultar inventario'],
            ['module' => 'admin.purchase_orders', 'label' => 'Crear pedido'],
            ['module' => 'admin.users.register', 'label' => 'Registrar usuario'],
            ['module' => 'admin.history', 'label' => 'Ver histórico'],
        ],
        'gerente' => [
            ['module' => 'gerente.solicitudes', 'label' => 'Revisar solicitudes'],
            ['module' => 'gerente.purchase_orders.consultar', 'label' => 'Consultar órdenes'],
            ['module' => 'admin.reports', 'label' => 'Ver reportes'],
        ],
        'almacenista' => [
            ['module' => 'admin.inventory.consulta', 'label' => 'Consultar inventario'],
            ['module' => 'almacenista.solicitar_repuestos', 'label' => 'Solicitar repuestos'],
            ['module' => 'almacenista.solicitudes', 'label' => 'Mis solicitudes'],
        ],
        'proveedor' => [
            ['module' => 'provider.purchase_orders', 'label' => 'Ver mis órdenes'],
        ],
    ];

    public function goTo(string $module): void
    {
        $role = Auth::user()?->role;
        $allowedModules = array_column(self::QUICK_ACTIONS[$role] ?? [], 'module');

        abort_unless(in_array($module, $allowedModules, true), 403);

        $this->dispatch('showModule', $module);
    }

    public function render()
    {
        $role = Auth::user()?->role;
        if ($role === 'proveedor') {
            $orderStatusCounts = PurchaseOrder::query()
                ->where('provider_id', Auth::id())
                ->select('status')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($total) => (int) $total);

            return view('livewire.provider.dashboard', [
                'firstName' => preg_split('/\s+/', trim(Auth::user()->name))[0] ?? Auth::user()->name,
                'recentOrders' => PurchaseOrder::query()
                    ->with('items')
                    ->where('provider_id', Auth::id())
                    ->latest('created_at')
                    ->limit(5)
                    ->get(),
                'statusCounts' => [
                    'all' => (int) $orderStatusCounts->sum(),
                    'pending' => (int) $orderStatusCounts->get('pendiente', 0),
                    'inProgress' => (int) $orderStatusCounts->get('en_proceso', 0),
                    'invoicesForReview' => (int) $orderStatusCounts->get('factura_subida', 0)
                        + (int) $orderStatusCounts->get('por_revisar', 0),
                    'approved' => (int) $orderStatusCounts->get('aprobado', 0),
                    'rejected' => (int) $orderStatusCounts->get('rechazado', 0),
                ],
            ]);
        }

        $canViewOperations = in_array($role, ['administrador', 'superadministrador', 'gerente'], true);
        $canViewMovements = in_array($role, ['administrador', 'superadministrador', 'gerente', 'almacenista'], true);

        $metrics = [
            'pendingRequests' => 0,
            'ordersWithoutInvoice' => 0,
            'invoicesForReview' => 0,
            'recentRejections' => 0,
        ];

        if ($canViewOperations) {
            $metrics['pendingRequests'] = SpareRequest::whereIn('status', ['pending', 'por_revisar'])
                ->where(function ($query) {
                    $query->whereNull('tipo')->orWhere('tipo', '<>', 'eliminacion');
                })
                ->count();
            $metrics['ordersWithoutInvoice'] = PurchaseOrder::whereNull('attachment')
                ->where('status', '<>', 'rechazado')
                ->count();
            $metrics['invoicesForReview'] = PurchaseOrder::whereNotNull('attachment')
                ->whereIn('status', ['factura_subida', 'por_revisar'])
                ->count();
            $recentRejectionsSince = now()->subDays(30);
            $metrics['recentRejections'] = SpareRequest::whereNotNull('reject_reason')
                ->where('created_at', '>=', $recentRejectionsSince)
                ->count()
                + PurchaseOrder::whereNotNull('reject_reason')
                    ->where('updated_at', '>=', $recentRejectionsSince)
                    ->count();
        }

        $movementsService = app(DashboardMovements::class);
        $movements = null;
        if ($movementsService->available() && $canViewMovements) {
            $weekly = $movementsService->weekly();
            $movements = [
                'weekly' => $weekly,
                'summary' => $movementsService->summary($weekly),
                'recentExits' => $movementsService->recentExits(5),
                'topItems' => $movementsService->topItems(),
                'topVehicles' => $canViewOperations ? $movementsService->topVehicles() : collect(),
                'monthlyOrders' => $canViewOperations ? $movementsService->monthlyOrders() : null,
            ];
        }

        return view('livewire.home', [
            'firstName' => preg_split('/\s+/', trim(Auth::user()->name))[0] ?? Auth::user()->name,
            'canViewOperations' => $canViewOperations,
            'hasStockThresholds' => Schema::hasTable('inventory_items')
                && Schema::hasColumn('inventory_items', 'stock_minimo'),
            'quickActions' => self::QUICK_ACTIONS[$role] ?? [],
            'movements' => $movements,
            ...$metrics,
        ]);
    }
}
