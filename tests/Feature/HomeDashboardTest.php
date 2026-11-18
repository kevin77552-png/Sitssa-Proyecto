<?php

namespace Tests\Feature;

use App\Livewire\DynamicModule;
use App\Livewire\Home;
use App\Livewire\Sidebar;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Models\User;
use App\Services\InventoryMovementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
        InventoryMovementRecorder::resetCache();
    }

    public function test_operations_dashboard_shows_metrics_for_administrators(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Home::class)
            ->assertSee('Buen día')
            ->assertSee('Solicitudes pendientes')
            ->assertSee('Crear pedido');
    }

    public function test_provider_dashboard_hides_operations_metrics(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);

        Livewire::actingAs($provider)
            ->test(Home::class)
            ->assertSee('Ver mis órdenes')
            ->assertSee('Portal de proveedores')
            ->assertSee('Resumen de estados')
            ->assertDontSee('Solicitudes pendientes');
    }

    public function test_provider_dashboard_shows_live_counts_and_only_its_recent_orders(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);
        $otherProvider = User::factory()->create(['role' => 'proveedor']);
        PurchaseOrder::create([
            'cantidad' => 3,
            'descripcion' => 'Filtro asignado a este proveedor',
            'provider_id' => $provider->id,
            'status' => 'pendiente',
        ]);
        PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Bomba con factura en revisión',
            'provider_id' => $provider->id,
            'status' => 'factura_subida',
        ]);
        PurchaseOrder::create([
            'cantidad' => 8,
            'descripcion' => 'Orden de otro proveedor',
            'provider_id' => $otherProvider->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($provider)
            ->test(Home::class)
            ->assertSee('Filtro asignado a este proveedor')
            ->assertSee('Bomba con factura en revisión')
            ->assertSee('Órdenes pendientes')
            ->assertSee('Facturas por revisar')
            ->assertSee('2 órdenes en total')
            ->assertDontSee('Orden de otro proveedor');
    }

    public function test_quick_actions_cannot_be_used_across_roles(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);

        Livewire::actingAs($provider)
            ->test(Home::class)
            ->call('goTo', 'admin.history')
            ->assertForbidden();
    }

    public function test_dashboard_only_renders_when_no_module_is_selected(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(DynamicModule::class)
            ->assertSee('Buen día')
            ->call('showModule', 'admin.history')
            ->assertDontSee('Buen día')
            ->call('showHome')
            ->assertSee('Buen día');
    }

    public function test_sidebar_home_action_clears_the_selected_module(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Sidebar::class)
            ->call('select', 'admin.history')
            ->assertSet('selected', 'admin.history')
            ->call('goHome')
            ->assertSet('selected', null)
            ->assertDispatched('showHome');
    }

    public function test_dashboard_route_renders_home_once_without_breadcrumbs(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        $response = $this->actingAs($administrator)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Buen día')
            ->assertDontSee('Usuarios ›')
            ->assertDontSee('Ordenes de compra >');

        $this->assertSame(1, substr_count($response->getContent(), 'Buen día'));
    }

    public function test_weekly_movement_chart_is_shown_to_administrators_and_stockkeepers(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(3)->setTime(12, 0));
        $item = InventoryItem::create(['sigicov' => '7001', 'descripcion' => 'FILTRO', 'inventario' => '4']);
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'tipo' => 'entrada',
            'delta' => 4,
            'stock_antes' => 0,
            'stock_despues' => 4,
            'occurred_at' => now(),
        ]);

        foreach (['administrador', 'almacenista'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test(Home::class)
                ->assertSee('Entradas y salidas por semana')
                ->assertSee('Entradas')
                ->assertSee('Salidas')
                ->assertSee('Entradas: 4', false);
        }
    }

    public function test_monthly_requests_and_orders_chart_is_shown_to_operations_roles(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(5)->setTime(12, 0));
        $requester = User::factory()->create(['role' => 'almacenista']);
        SpareRequest::unguarded(fn () => SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 2,
            'descripcion' => 'Solicitud para gráfico',
            'tipo' => 'compra',
            'status' => 'pending',
        ]));
        PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Orden para gráfico',
            'pedido_por' => $requester->name,
            'status' => 'pendiente',
        ]);

        foreach (['administrador', 'gerente'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test(Home::class)
                ->assertSee('Solicitudes y órdenes por mes')
                ->assertSee('Solicitudes')
                ->assertSee('Órdenes')
                ->assertSee('Solicitudes: 1', false)
                ->assertSee('Órdenes: 1', false);
        }
    }

    public function test_dashboard_omits_movement_chart_when_table_does_not_exist(): void
    {
        Schema::dropIfExists('inventory_movements');
        InventoryMovementRecorder::resetCache();
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Home::class)
            ->assertDontSee('Entradas y salidas por semana')
            ->assertDontSee('Solicitudes y órdenes por mes');
    }
}
