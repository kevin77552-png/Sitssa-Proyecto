<?php

namespace Tests\Feature;

use App\Livewire\Admin\PurchaseOrdersConsulta as AdminPurchaseOrdersConsulta;
use App\Livewire\Gerente\PurchaseOrdersConsulta as GerentePurchaseOrdersConsulta;
use App\Livewire\Admin\UserList;
use App\Livewire\DynamicModule;
use App\Livewire\Provider\PurchaseOrders as ProviderPurchaseOrders;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_provider_component_only_renders_its_assigned_orders(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);
        $otherProvider = User::factory()->create(['role' => 'proveedor']);
        $ownOrder = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Visible assigned order',
            'provider_id' => $provider->id,
        ]);
        PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Hidden other order',
            'provider_id' => $otherProvider->id,
        ]);

        Livewire::actingAs($provider)
            ->test(ProviderPurchaseOrders::class)
            ->assertSee($ownOrder->descripcion)
            ->assertDontSee('Hidden other order');
    }

    public function test_provider_can_filter_and_search_only_their_assigned_orders(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);
        $otherProvider = User::factory()->create(['role' => 'proveedor']);
        PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Filtro de aceite pendiente',
            'provider_id' => $provider->id,
            'status' => 'pendiente',
        ]);
        PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Bomba hidráulica en proceso',
            'provider_id' => $provider->id,
            'status' => 'en_proceso',
        ]);
        PurchaseOrder::create([
            'cantidad' => 3,
            'descripcion' => 'Filtro de aire de otro proveedor',
            'provider_id' => $otherProvider->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($provider)
            ->test(ProviderPurchaseOrders::class)
            ->assertSee('Total de órdenes')
            ->set('statusFilter', 'pendiente')
            ->assertSee('Filtro de aceite pendiente')
            ->assertDontSee('Bomba hidráulica en proceso')
            ->set('statusFilter', 'all')
            ->set('search', 'bomba')
            ->assertSee('Bomba hidráulica en proceso')
            ->assertDontSee('Filtro de aceite pendiente')
            ->assertDontSee('Filtro de aire de otro proveedor')
            ->call('clearFilters')
            ->assertSee('Filtro de aceite pendiente')
            ->assertSee('Bomba hidráulica en proceso')
            ->assertDontSee('Filtro de aire de otro proveedor');
    }

    public function test_marking_an_order_as_seen_is_safe_to_repeat(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);
        $order = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Orden pendiente',
            'provider_id' => $provider->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($provider)
            ->test(ProviderPurchaseOrders::class)
            ->call('markAsSeen', $order->id)
            ->assertHasNoErrors()
            ->call('markAsSeen', $order->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'status' => 'en_proceso',
        ]);
        $this->assertSame(1, \App\Models\Log::where('accion', 'marcar_visto')
            ->where('entidad_id', $order->id)
            ->count());
    }

    public function test_manager_can_filter_and_search_purchase_orders(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Filtro pendiente de factura',
            'status' => 'pendiente',
        ]);
        PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Bomba con factura',
            'status' => 'factura_subida',
            'attachment' => 'invoices/bomba.pdf',
        ]);

        Livewire::actingAs($manager)
            ->test(GerentePurchaseOrdersConsulta::class)
            ->assertSee('Total de órdenes')
            ->set('statusFilter', 'por_revisar')
            ->assertSee('Bomba con factura')
            ->assertDontSee('Filtro pendiente de factura')
            ->set('statusFilter', 'all')
            ->set('search', 'bomba')
            ->assertSee('Bomba con factura')
            ->assertDontSee('Filtro pendiente de factura')
            ->call('clearFilters')
            ->assertSee('Filtro pendiente de factura')
            ->assertSee('Bomba con factura');
    }

    public function test_manager_can_assign_an_active_provider_to_an_unassigned_order(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $provider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);
        $order = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Orden sin proveedor',
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)
            ->test(GerentePurchaseOrdersConsulta::class)
            ->call('openAssignProvider', $order->id)
            ->set("providerIds.{$order->id}", $provider->id)
            ->call('assignProvider', $order->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_manager_can_export_the_current_purchase_order_filter(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);

        Livewire::actingAs($manager)
            ->test(GerentePurchaseOrdersConsulta::class)
            ->set('statusFilter', 'facturada')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_administrator_cannot_promote_a_user_to_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $target = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($admin)
            ->test(UserList::class)
            ->call('editUser', $target->id)
            ->set('editRole', 'administrador')
            ->call('updateUser')
            ->assertHasErrors('editRole');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'almacenista',
        ]);
    }

    public function test_admin_can_assign_an_unassigned_order_to_an_active_provider(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $provider = User::factory()->create(['role' => 'proveedor', 'activo' => true]);
        $order = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Unassigned order',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminPurchaseOrdersConsulta::class)
            ->set("providerIds.{$order->id}", $provider->id)
            ->call('assignProvider', $order->id);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_admin_can_search_orders_and_filter_by_provider_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $provider = User::factory()->create(['role' => 'proveedor', 'name' => 'Proveedor de filtros']);
        $assignedOrder = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Filtro de aceite asignado',
            'provider_id' => $provider->id,
        ]);
        $unassignedOrder = PurchaseOrder::create([
            'cantidad' => 2,
            'descripcion' => 'Manguera sin proveedor',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminPurchaseOrdersConsulta::class)
            ->assertSee($assignedOrder->descripcion)
            ->assertSee($unassignedOrder->descripcion)
            ->set('search', 'Proveedor de filtros')
            ->assertSee($assignedOrder->descripcion)
            ->assertDontSee($unassignedOrder->descripcion)
            ->set('search', '')
            ->set('providerFilter', 'unassigned')
            ->assertSee($unassignedOrder->descripcion)
            ->assertDontSee($assignedOrder->descripcion);
    }

    public function test_admin_sees_validation_error_when_assigning_an_invalid_provider(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $order = PurchaseOrder::create([
            'cantidad' => 1,
            'descripcion' => 'Order requiring a provider',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminPurchaseOrdersConsulta::class)
            ->set("providerIds.{$order->id}", 999999)
            ->call('assignProvider', $order->id)
            ->assertHasErrors("providerIds.{$order->id}");

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'provider_id' => null,
        ]);
    }

    public function test_inventory_import_endpoint_is_removed(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($administrator)
            ->post('/inventory/import')
            ->assertNotFound();
    }

    public function test_provider_cannot_select_administrator_modules(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);

        Livewire::actingAs($provider)
            ->test(DynamicModule::class)
            ->call('showModule', 'admin.users')
            ->assertForbidden();
    }

    public function test_disabled_user_is_logged_out_on_the_next_web_request(): void
    {
        $user = User::factory()->create(['activo' => false]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invoice_migration_moves_files_out_of_public_storage(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        Storage::disk('public')->put('invoices/old-invoice.pdf', $contents);

        Artisan::call('invoices:secure-storage');

        $this->assertSame($contents, Storage::disk('local')->get('invoices/old-invoice.pdf'));
        $this->assertFalse(Storage::disk('public')->exists('invoices/old-invoice.pdf'));
    }
}
