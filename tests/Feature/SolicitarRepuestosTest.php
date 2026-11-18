<?php

namespace Tests\Feature;

use App\Livewire\Almacenista\SolicitarRepuestos;
use App\Livewire\Gerente\Solicitudes as GerenteSolicitudes;
use App\Livewire\Sidebar;
use App\Models\PurchaseOrder;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SolicitarRepuestosTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_form_saves_priority_and_displays_the_real_request(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista', 'name' => 'Ana Bodega']);

        $this->withoutVite()
            ->actingAs($warehouse)
            ->get(route('dashboard', ['module' => 'almacenista.solicitar_repuestos']))
            ->assertOk()
            ->assertSee('Solicitar repuestos')
            ->assertSee('Solicitado por')
            ->assertSee('Agregar repuesto')
            ->assertSee('Solicitudes recientes');

        Livewire::actingAs($warehouse)
            ->test(SolicitarRepuestos::class)
            ->assertSee('Ana Bodega')
            ->set('cantidad', 3)
            ->set('descripcion', 'Manguera de presión')
            ->set('detalles', 'Para reponer una unidad dañada')
            ->set('solicitadoPor', 'Luis Pérez')
            ->set('prioridad', 'URGENTE')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Solicitud creada correctamente')
            ->assertDontSee('Solicitud creada correctamente (ID:')
            ->assertSee('REQ-001')
            ->assertSee('Pendiente de revisión')
            ->assertSee('Urgente')
            ->assertSet('cantidad', 1)
            ->assertSet('prioridad', 'NORMAL');

        $request = SpareRequest::query()->where('almacenista_id', $warehouse->id)->firstOrFail();
        $this->assertDatabaseHas('spare_requests', [
            'id' => $request->id,
            'cantidad' => 3,
            'descripcion' => 'Manguera de presión',
            'solicitado_por' => 'Luis Pérez',
            'prioridad' => 'URGENTE',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('logs', [
            'accion' => 'crear_solicitud',
            'entidad_id' => $request->id,
            'user_id' => $warehouse->id,
        ]);

        $manager = User::factory()->create(['role' => 'gerente']);
        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->assertSee('Urgente')
            ->assertSee('Manguera de presión');
    }

    public function test_quantity_controls_and_clear_action_keep_values_in_range(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($warehouse)
            ->test(SolicitarRepuestos::class)
            ->call('adjustQuantity', -1)
            ->assertSet('cantidad', 1)
            ->set('cantidad', 999)
            ->call('adjustQuantity', 1)
            ->assertSet('cantidad', 999)
            ->set('descripcion', 'Filtro')
            ->set('detalles', 'Detalles')
            ->set('solicitadoPor', 'Solicitante de prueba')
            ->set('prioridad', 'URGENTE')
            ->call('clearForm')
            ->assertSet('cantidad', 1)
            ->assertSet('descripcion', '')
            ->assertSet('detalles', '')
            ->assertSet('solicitadoPor', '')
            ->assertSet('prioridad', 'NORMAL');
    }

    public function test_request_quantity_cannot_exceed_the_form_limit(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($warehouse)
            ->test(SolicitarRepuestos::class)
            ->set('items.0.descripcion', 'Filtro')
            ->set('items.0.cantidad', 1000)
            ->call('submit')
            ->assertHasErrors(['items.0.cantidad' => 'max']);

        $this->assertDatabaseCount('spare_requests', 0);
    }

    public function test_multiple_requested_items_create_one_order_with_their_details(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);

        Livewire::actingAs($warehouse)
            ->test(SolicitarRepuestos::class)
            ->set('items.0.descripcion', 'Filtro de aceite')
            ->set('items.0.cantidad', 2)
            ->set('items.0.detalles', 'Motor diésel')
            ->set('solicitadoPor', 'María López')
            ->call('addItem')
            ->set('items.1.descripcion', 'Bomba de agua')
            ->set('items.1.cantidad', 1)
            ->set('items.1.detalles', 'Con empaque')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Solicitud creada correctamente');

        $request = SpareRequest::with('items')->where('almacenista_id', $warehouse->id)->firstOrFail();
        $this->assertSame(3, $request->cantidad);
        $this->assertCount(2, $request->items);

        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->assertSee('Filtro de aceite')
            ->assertSee('Bomba de agua')
            ->assertSee('María López')
            ->set("providerIds.{$request->id}", $provider->id)
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $order = PurchaseOrder::with('items')->where('spare_request_id', $request->id)->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(3, $order->cantidad);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'descripcion' => 'Filtro de aceite',
            'cantidad' => 2,
            'detalles' => 'Motor diésel',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'descripcion' => 'Bomba de agua',
            'cantidad' => 1,
            'detalles' => 'Con empaque',
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'solicitado_por' => 'María López',
            'pedido_por' => $manager->name,
        ]);
        $this->assertDatabaseHas('logs', [
            'entidad' => 'purchase_order',
            'entidad_id' => $order->id,
            'entidad_resumen' => 'Orden #'.$order->id
                .' - Filtro de aceite / Bomba de agua'
                .' - Solicitada por: María López'
                .' - Emitida por: '.$manager->name,
        ]);
    }

    public function test_manager_sidebar_shows_pending_request_count(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $manager = User::factory()->create(['role' => 'gerente']);
        SpareRequest::create([
            'almacenista_id' => $warehouse->id,
            'descripcion' => 'Filtro',
            'cantidad' => 1,
            'solicitado_por' => 'María López',
            'status' => 'pending',
        ]);

        Livewire::actingAs($manager)
            ->test(Sidebar::class)
            ->assertSee('1 solicitud pendiente');
    }

    public function test_request_form_limits_the_number_of_items_to_twenty(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $form = Livewire::actingAs($warehouse)->test(SolicitarRepuestos::class);

        for ($index = 1; $index < 20; $index++) {
            $form->call('addItem');
        }

        $form->assertCount('items', 20)
            ->call('addItem')
            ->assertHasErrors(['items']);
    }

    public function test_manager_requests_can_be_filtered_and_searched_by_request_code(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $manager = User::factory()->create(['role' => 'gerente']);

        foreach ([
            ['Manguera para U-104', 'pending', null],
            ['Disco de freno', 'approved', null],
            ['Filtro de aceite', 'received', null],
            ['Bomba de agua', 'rejected', null],
            ['Solicitud de eliminación', 'pending', 'eliminacion'],
        ] as [$description, $status, $type]) {
            SpareRequest::create([
                'cantidad' => 1,
                'descripcion' => $description,
                'almacenista_id' => $warehouse->id,
                'status' => $status,
                'tipo' => $type,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->assertSee('Todas (4)')
            ->assertSee('Pendientes (1)')
            ->assertSee('Aprobadas (2)')
            ->assertSee('Rechazadas (1)')
            ->call('setStatusFilter', 'approved')
            ->assertSee('Disco de freno')
            ->assertSee('Filtro de aceite')
            ->assertDontSee('Manguera para U-104')
            ->call('setStatusFilter', 'all')
            ->set('search', 'REQ-001')
            ->assertSee('Manguera para U-104')
            ->assertDontSee('Disco de freno');
    }

    public function test_manager_can_change_requests_page_size(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);

        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->set('perPage', '25')
            ->assertSet('perPage', 25)
            ->assertHasNoErrors();
    }

    public function test_manager_can_approve_a_request_with_an_active_provider(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);
        $request = SpareRequest::create([
            'cantidad' => 2,
            'descripcion' => 'Manguera hidráulica',
            'almacenista_id' => $warehouse->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->set("providerIds.{$request->id}", $provider->id)
            ->call('approve', $request->id)
            ->assertSee('Solicitud aprobada y orden de compra creada')
            ->assertDontSee('Solicitud aprobada y orden de compra creada (ID:')
            ->assertSee($provider->name)
            ->assertSee('OC #');

        $this->assertDatabaseHas('spare_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'spare_request_id' => $request->id,
            'provider_id' => $provider->id,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'descripcion' => 'Manguera hidráulica',
            'cantidad' => 2,
        ]);
        $this->assertSame(1, PurchaseOrder::where('spare_request_id', $request->id)->count());
    }

    public function test_manager_can_reject_a_request_with_a_reason(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $manager = User::factory()->create(['role' => 'gerente']);
        $request = SpareRequest::create([
            'cantidad' => 1,
            'descripcion' => 'Filtro de combustible',
            'almacenista_id' => $warehouse->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($manager)
            ->test(GerenteSolicitudes::class)
            ->call('openReject', $request->id)
            ->assertSee('Rechazar solicitud')
            ->set('rejectReason', 'Ya existe disponibilidad en almacén.')
            ->call('submitReject')
            ->assertSee('Solicitud rechazada.');

        $this->assertDatabaseHas('spare_requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'reject_reason' => 'Ya existe disponibilidad en almacén.',
        ]);
    }
}
