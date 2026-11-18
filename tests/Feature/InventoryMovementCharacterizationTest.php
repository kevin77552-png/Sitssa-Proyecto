<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryEgreso;
use App\Livewire\Gerente\Eliminaciones;
use App\Models\InventoryItem;
use App\Models\Log;
use App\Models\SpareRequest;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\InventoryMovementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMovementCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
        InventoryMovementRecorder::resetCache();
    }

    public function test_egreso_decrements_stock_and_creates_existing_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('10');
        $vehicle = Vehiculo::create([
            'placa' => 'ABC-123',
            'descripcion' => 'Camión blanco',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id)
            ->set('item_id', $item->id)
            ->set('vehiculo_id', $vehicle->id)
            ->set('motivo_tipo', 'correctivo')
            ->call('confirmSalida')
            ->assertHasNoErrors()
            ->assertSet('success', 'Entrega registrada correctamente.');

        $this->assertSame('9', $item->fresh()->inventario);
        $this->assertDatabaseHas('logs', [
            'accion' => 'salida',
            'entidad' => 'inventory_item',
            'entidad_id' => $item->id,
            'descripcion' => 'Salida de 1 unidades para vehículo: Placa ABC-123, Descripción: Camión blanco | Motivo: Mantenimiento correctivo',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'vehiculo_id' => $vehicle->id,
            'delta' => -1,
            'motivo_tipo' => 'correctivo',
        ]);
    }

    public function test_egreso_row_action_opens_the_confirmation_modal(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '10');

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->assertSee('wire:click="openSalidaModal('.$item->id.')"', false)
            ->call('openSalidaModal', $item->id)
            ->assertSet('showSalidaModal', true)
            ->assertSee('Registrar entrega')
            ->assertSee('Confirmar entrega de repuesto')
            ->assertSee('Confirmar entrega');
    }

    public function test_egreso_with_insufficient_stock_is_rejected_without_changing_stock_or_logging(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('0');
        $vehicle = Vehiculo::create([
            'placa' => 'ABC-123',
            'descripcion' => 'Camión blanco',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id)
            ->set('item_id', $item->id)
            ->set('vehiculo_id', $vehicle->id)
            ->set('motivo_tipo', 'preventivo')
            ->call('confirmSalida')
            ->assertHasNoErrors()
            ->assertSet('error', 'No hay existencias disponibles para registrar esta entrega.');

        $this->assertSame('0', $item->fresh()->inventario);
        $this->assertSame(0, Log::where('accion', 'salida')->count());
        $this->assertSame(0, \App\Models\InventoryMovement::count());
    }

    public function test_egreso_search_filters_by_description_and_peldano(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('10');
        $item->update(['peldaño' => 'B']);
        $otherItem = InventoryItem::create([
            'sigicov' => '2002',
            'descripcion' => 'BOMBA DE AGUA',
            'peldaño' => 'C',
            'inventario' => '4',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->assertSee('Buscar por SIGICOV o descripción')
            ->set('search', 'filtro')
            ->assertSee($item->sigicov)
            ->assertDontSee($otherItem->sigicov)
            ->set('search', '')
            ->set('search_peldano', 'c')
            ->assertSee($otherItem->sigicov)
            ->assertDontSee($item->sigicov);
    }

    public function test_approved_elimination_decrements_stock_and_preserves_both_audit_logs(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('10');
        $request = SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 4,
            'descripcion' => 'Daño en bodega',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)
            ->test(Eliminaciones::class)
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $this->assertSame('6', $item->fresh()->inventario);
        $this->assertSame('aprobada', $request->fresh()->status);
        $this->assertDatabaseHas('logs', [
            'accion' => 'ajustar_inventario_por_eliminacion',
            'entidad' => 'inventory_item',
            'entidad_id' => $item->id,
            'descripcion' => "Solicitud {$request->id}: inventario 10 => 6 (se removieron 4)",
            'user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('logs', [
            'accion' => 'aprobar_eliminacion',
            'entidad' => 'spare_request',
            'entidad_id' => $request->id,
            'descripcion' => 'Solicitud de eliminacion aprobada por gerente',
            'user_id' => $manager->id,
        ]);
    }

    public function test_approved_elimination_that_uses_the_last_unit_archives_item(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1');
        $request = SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 1,
            'descripcion' => 'Daño en bodega',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)
            ->test(Eliminaciones::class)
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $this->assertSame('aprobada', $request->fresh()->status);
        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
        $this->assertSame('0', InventoryItem::withTrashed()->findOrFail($item->id)->inventario);
    }

    public function test_elimination_queue_counts_filter_and_search_match_inventory_data(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista', 'name' => 'Ana Bodega']);
        $item = $this->createItem('10');
        $item->update([
            'sigicov' => '12821',
            'pasillo' => 'Techo',
            'estante' => 'ALM.1',
            'peldaño' => 'B',
        ]);

        foreach ([
            [$item, 'Empaque dañado', 'pendiente'],
            [InventoryItem::create(['sigicov' => '12822', 'descripcion' => 'APROBADO', 'inventario' => '4']), 'Repuesto aprobado', 'aprobada'],
            [InventoryItem::create(['sigicov' => '12823', 'descripcion' => 'RECHAZADO', 'inventario' => '3']), 'Repuesto rechazado', 'rechazada'],
        ] as [$inventoryItem, $reason, $status]) {
            SpareRequest::create([
                'almacenista_id' => $requester->id,
                'cantidad' => 1,
                'descripcion' => 'Solicitud de eliminación '.$inventoryItem->sigicov,
                'detalles' => $reason,
                'tipo' => 'eliminacion',
                'inventory_item_id' => $inventoryItem->id,
                'status' => $status,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(Eliminaciones::class)
            ->assertSee('Todos (3)')
            ->assertSee('Por aprobar (1)')
            ->assertSee('Aprobadas (1)')
            ->assertSee('Rechazadas (1)')
            ->assertSee('SIGICOV', false)
            ->assertSee('ALM.1')
            ->call('setStatusFilter', 'por_aprobar')
            ->assertSee('Empaque dañado')
            ->assertDontSee('Repuesto aprobado')
            ->set('statusFilter', 'todos')
            ->set('search', '12821')
            ->assertSee('Empaque dañado')
            ->assertDontSee('Repuesto aprobado');
    }

    public function test_elimination_approval_confirmation_records_optional_audit_note(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('10');
        $request = SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 4,
            'descripcion' => 'Solicitud de eliminación 1001',
            'detalles' => 'Pieza deteriorada',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)
            ->test(Eliminaciones::class)
            ->call('openApproveModal', $request->id)
            ->assertSee('Confirmar eliminación')
            ->assertSee('Pieza deteriorada')
            ->set('auditNote', 'Verificado físicamente')
            ->call('confirmAction')
            ->assertHasNoErrors()
            ->assertSet('showRejectModal', false);

        $this->assertSame('6', $item->fresh()->inventario);
        $this->assertDatabaseHas('logs', [
            'accion' => 'aprobar_eliminacion',
            'entidad_id' => $request->id,
            'descripcion' => 'Solicitud de eliminacion aprobada por gerente. Nota: Verificado físicamente',
            'user_id' => $manager->id,
        ]);
    }

    public function test_elimination_rejection_requires_a_reason_and_records_it(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('10');
        $request = SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 1,
            'descripcion' => 'Solicitud de eliminación 1001',
            'detalles' => 'Revisar disponibilidad',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)
            ->test(Eliminaciones::class)
            ->call('openRejectModal', $request->id)
            ->set('auditNote', '')
            ->call('confirmAction')
            ->assertHasErrors('rejectReason')
            ->set('auditNote', 'El repuesto todavía está en buen estado.')
            ->call('confirmAction')
            ->assertHasNoErrors()
            ->assertSet('showRejectModal', false);

        $this->assertDatabaseHas('spare_requests', [
            'id' => $request->id,
            'status' => 'rechazada',
            'reject_reason' => 'El repuesto todavía está en buen estado.',
        ]);
    }

    private function createItem(string $stock): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => '1001',
            'descripcion' => 'FILTRO DE ACEITE',
            'inventario' => $stock,
        ]);
    }
}
