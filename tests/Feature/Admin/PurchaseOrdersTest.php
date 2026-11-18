<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\PurchaseOrders;
use App\Livewire\Provider\PurchaseOrders as ProviderPurchaseOrders;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_screen_renders_real_actor_provider_and_recent_order(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create([
            'name' => 'Proveedor Autorizado',
            'role' => 'proveedor',
            'activo' => true,
        ]);
        $order = PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Filtros de aceite',
            'pedido_por' => $manager->name,
            'status' => 'pendiente',
            'provider_id' => $provider->id,
        ]);

        Livewire::actingAs($manager)
            ->test(PurchaseOrders::class)
            ->assertSee('Órdenes de compra')
            ->assertSee($manager->name)
            ->assertSee($provider->name)
            ->assertSee($order->descripcion)
            ->assertSee('Pendiente');
    }

    public function test_manager_can_create_order_with_active_provider_and_real_values(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create([
            'role' => 'proveedor',
            'activo' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(PurchaseOrders::class)
            ->set('descripcion', 'Mangueras hidráulicas')
            ->set('detalles', 'Entrega urgente')
            ->set('cantidad', 3)
            ->set('providerId', (string) $provider->id)
            ->call('createOrder')
            ->assertHasNoErrors()
            ->assertSee('Pedido #1 creado correctamente.');

        $this->assertDatabaseHas('purchase_orders', [
            'cantidad' => 3,
            'descripcion' => 'Mangueras hidráulicas',
            'detalles' => 'Entrega urgente',
            'solicitado_por' => null,
            'pedido_por' => $manager->name,
            'provider_id' => $provider->id,
            'status' => 'pendiente',
        ]);
        $this->assertDatabaseHas('logs', [
            'entidad' => 'purchase_order',
            'entidad_id' => 1,
            'entidad_resumen' => 'Orden #1 - Mangueras hidráulicas - Emitida por: '.$manager->name,
        ]);
    }

    public function test_manager_can_create_an_order_with_multiple_spare_parts(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create([
            'role' => 'proveedor',
            'activo' => true,
        ]);

        Livewire::actingAs($manager)
            ->test(PurchaseOrders::class)
            ->set('items', [
                ['descripcion' => 'Filtro de aceite', 'cantidad' => 2],
                ['descripcion' => 'Pastillas de freno', 'cantidad' => 4],
            ])
            ->set('providerId', (string) $provider->id)
            ->call('createOrder')
            ->assertHasNoErrors()
            ->assertSee('Pedido #1 creado correctamente.');

        $order = PurchaseOrder::query()->with('items')->sole();
        $this->assertSame('Filtro de aceite, Pastillas de freno', $order->descripcion);
        $this->assertSame(6, $order->cantidad);
        $this->assertCount(2, $order->items);
        $this->assertSame([
            ['Filtro de aceite', 2],
            ['Pastillas de freno', 4],
        ], $order->items->map(fn ($item) => [$item->descripcion, $item->cantidad])->all());

        Livewire::actingAs($provider)
            ->test(ProviderPurchaseOrders::class)
            ->assertSee('Filtro de aceite')
            ->assertSee('Pastillas de freno')
            ->assertSee('Unidades: 6');
    }

    public function test_order_requires_an_active_provider(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);

        Livewire::actingAs($manager)
            ->test(PurchaseOrders::class)
            ->set('descripcion', 'Repuesto sin proveedor')
            ->call('createOrder')
            ->assertHasErrors(['providerId']);

        $this->assertDatabaseMissing('purchase_orders', [
            'descripcion' => 'Repuesto sin proveedor',
        ]);
    }

    public function test_clear_form_resets_values_and_validation_errors(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);

        Livewire::actingAs($manager)
            ->test(PurchaseOrders::class)
            ->set('descripcion', 'Borrador')
            ->set('cantidad', 4)
            ->set('detalles', 'Borrador de detalle')
            ->set('providerId', '99')
            ->call('createOrder')
            ->assertHasErrors('providerId')
            ->call('resetForm')
            ->assertSet('descripcion', '')
            ->assertSet('cantidad', 1)
            ->assertSet('detalles', '')
            ->assertSet('providerId', '')
            ->assertHasNoErrors();
    }
}
