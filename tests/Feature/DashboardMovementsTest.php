<?php

namespace Tests\Feature;

use App\Livewire\Home;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\InventoryMovementRecorder;
use App\Support\DashboardMovements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardMovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
        InventoryMovementRecorder::resetCache();
    }

    public function test_stockkeeper_without_movements_sees_empty_states_and_zero_tiles(): void
    {
        Livewire::actingAs($this->user('almacenista'))
            ->test(Home::class)
            ->assertSee('Salidas hoy')
            ->assertSee('>0</p>', false)
            ->assertSee('Aún no hay entradas ni salidas')
            ->assertSee('Aún no hay egresos')
            ->assertSee('Más usados este mes');
    }

    public function test_stockkeeper_sees_today_exit_vehicle_and_updated_summary(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0));
        $vehicle = Vehiculo::create(['placa' => 'ABC-123', 'descripcion' => 'Camión blanco']);
        $this->movement('salida', -2, now(), $vehicle);

        Livewire::actingAs($this->user('almacenista'))
            ->test(Home::class)
            ->assertSee('Salidas hoy')
            ->assertSee('>2</p>', false)
            ->assertSee('Últimos egresos')
            ->assertSee('ABC-123')
            ->assertSee('Camión blanco')
            ->assertDontSee('Aún no hay egresos');
    }

    public function test_administrator_sees_charts_and_read_only_usage_rankings(): void
    {
        $vehicle = Vehiculo::create(['placa' => 'XYZ-987', 'descripcion' => 'Bus']);
        $item = $this->item();
        $this->movement('salida', -3, now(), $vehicle, $item);
        SpareRequest::unguarded(fn () => SpareRequest::create([
            'almacenista_id' => $this->user('almacenista')->id,
            'cantidad' => 1,
            'descripcion' => 'Solicitud para dashboard',
            'tipo' => 'compra',
            'status' => 'pending',
        ]));
        PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Orden para dashboard',
            'pedido_por' => 'Prueba',
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($this->user('administrador'))
            ->test(Home::class)
            ->assertSee('Entradas y salidas por semana')
            ->assertSee('Solicitudes y órdenes por mes')
            ->assertSee('Consumo por vehículo')
            ->assertSee('XYZ-987')
            ->assertSee('Repuestos más usados')
            ->assertSee('FILTRO DE ACEITE');

        $this->assertSame(1, InventoryItem::count());
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_dashboard_hides_movement_blocks_when_recorder_is_disabled(): void
    {
        config(['inventory_movements.enabled' => false]);

        Livewire::actingAs($this->user('almacenista'))
            ->test(Home::class)
            ->assertOk()
            ->assertDontSee('Entradas y salidas por semana')
            ->assertDontSee('Salidas hoy')
            ->assertDontSee('Últimos egresos');
    }

    public function test_weekly_movements_are_assigned_to_their_monday_based_bucket(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(3)->setTime(12, 0));
        $item = $this->item();
        $this->movement('entrada', 5, now(), item: $item);
        $this->movement('entrada', 3, now()->subWeeks(3), item: $item);
        $this->movement('salida', -4, now(), item: $item);

        $weekly = app(DashboardMovements::class)->weekly(8);

        $this->assertSame(5, $weekly['entradas'][7]);
        $this->assertSame(3, $weekly['entradas'][4]);
        $this->assertSame(4, $weekly['salidas'][7]);
        $this->assertCount(8, $weekly['labels']);
    }

    public function test_weekly_and_monthly_labels_are_readable_in_spanish(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-07-06 12:00:00'));

        $service = app(DashboardMovements::class);

        $this->assertSame('6 jul', $service->weekly()['labels'][7]);
        $this->assertSame('Jul 2026', $service->monthlyOrders()['labels'][5]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => '1001',
            'descripcion' => 'FILTRO DE ACEITE',
            'inventario' => '10',
        ]);
    }

    private function movement(
        string $type,
        int $delta,
        $at = null,
        ?Vehiculo $vehicle = null,
        ?InventoryItem $item = null,
    ): InventoryMovement {
        $item ??= $this->item();

        return InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'tipo' => $type,
            'delta' => $delta,
            'stock_antes' => 10,
            'stock_despues' => 10 + $delta,
            'vehiculo_id' => $vehicle?->id,
            'vehiculo_placa' => $vehicle?->placa,
            'vehiculo_descripcion' => $vehicle?->descripcion,
            'occurred_at' => $at ?? now(),
        ]);
    }
}
