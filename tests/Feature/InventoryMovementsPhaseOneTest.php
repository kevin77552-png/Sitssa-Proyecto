<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryEgreso;
use App\Livewire\Admin\InventoryIngreso;
use App\Livewire\Admin\InventoryRegister;
use App\Livewire\Gerente\Eliminaciones;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Log;
use App\Models\SpareRequest;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\InventoryMovementRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMovementsPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
        InventoryMovementRecorder::resetCache();
    }

    public function test_egreso_records_vehicle_movement_and_keeps_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '10');
        $vehicle = $this->createVehicle('ABC-123');

        $this->egreso($user, $item, $vehicle)->assertHasNoErrors();

        $movement = InventoryMovement::firstOrFail();
        $this->assertSame('salida', $movement->tipo);
        $this->assertSame(-1, $movement->delta);
        $this->assertSame(10, $movement->stock_antes);
        $this->assertSame(9, $movement->stock_despues);
        $this->assertSame('ABC-123', $movement->vehiculo_placa);
        $this->assertSame($vehicle->id, $movement->vehiculo_id);
        $this->assertSame('preventivo', $movement->motivo_tipo);
        $this->assertSame('9', $item->fresh()->inventario);
        $this->assertDatabaseHas('logs', ['accion' => 'salida', 'entidad_id' => $item->id]);
    }

    public function test_repeated_egresos_use_the_same_selected_catalog_vehicle(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '10');
        $vehicle = $this->createVehicle('ABC-123');

        $this->egreso($user, $item, $vehicle);
        $this->egreso($user, $item, $vehicle);

        $this->assertSame(1, Vehiculo::count());
        $this->assertSame(2, InventoryMovement::where('tipo', 'salida')->count());
        $this->assertSame('8', $item->fresh()->inventario);
    }

    public function test_insufficient_stock_does_not_change_stock_create_vehicle_or_movement(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '0');
        $vehicle = $this->createVehicle('XYZ-9');

        $this->egreso($user, $item, $vehicle)
            ->assertSet('error', 'No hay existencias disponibles para registrar esta entrega.');

        $this->assertSame('0', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(1, Vehiculo::count());
        $this->assertSame(0, Log::where('accion', 'salida')->count());
    }

    public function test_egreso_paginates_twenty_items_and_resets_page_when_search_changes(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        foreach (range(1, 21) as $number) {
            InventoryItem::create([
                'sigicov' => sprintf('SIG-%03d', $number),
                'descripcion' => 'Repuesto '.$number,
                'inventario' => 1,
            ]);
        }

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->assertSee('SIG-021')
            ->assertDontSee('SIG-001')
            ->call('nextPage')
            ->assertSee('SIG-001')
            ->assertDontSee('SIG-021')
            ->set('search', 'SIG-001')
            ->assertSee('SIG-001');
    }

    public function test_egreso_pagination_allows_changing_page_size_and_jumping_to_a_valid_page(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);
        foreach (range(1, 21) as $number) {
            InventoryItem::create([
                'sigicov' => sprintf('SIG-%03d', $number),
                'descripcion' => 'Repuesto '.$number,
                'inventario' => 1,
            ]);
        }

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->set('perPage', 15)
            ->assertSet('perPage', 15)
            ->assertSee('SIG-021')
            ->assertDontSee('SIG-006')
            ->set('pageJump', '2')
            ->call('jumpToPage')
            ->assertSee('SIG-006')
            ->assertSee('SIG-001')
            ->assertDontSee('SIG-007')
            ->set('pageJump', '3')
            ->call('jumpToPage')
            ->assertHasErrors('pageJump')
            ->assertSee('SIG-006');
    }

    public function test_disabled_recorder_preserves_egreso_behavior_without_movements(): void
    {
        config(['inventory_movements.enabled' => false]);
        InventoryMovementRecorder::resetCache();
        $user = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '10');
        $vehicle = $this->createVehicle('ABC-123');

        $this->egreso($user, $item, $vehicle)->assertHasNoErrors();

        $this->assertSame('9', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(1, Vehiculo::count());
        $this->assertTrue(Log::where('accion', 'salida')->exists());
    }

    public function test_approved_elimination_records_negative_adjustment_and_keeps_audit_logs(): void
    {
        $manager = User::factory()->create(['role' => 'gerente']);
        $requester = User::factory()->create(['role' => 'almacenista']);
        $item = $this->createItem('1001', '10');
        $request = SpareRequest::create([
            'almacenista_id' => $requester->id,
            'cantidad' => 4,
            'descripcion' => 'Daño',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
            'status' => 'pendiente',
        ]);

        Livewire::actingAs($manager)->test(Eliminaciones::class)->call('approve', $request->id);

        $this->assertSame('6', $item->fresh()->inventario);
        $movement = InventoryMovement::firstOrFail();
        $this->assertSame('ajuste', $movement->tipo);
        $this->assertSame(-4, $movement->delta);
        $this->assertSame(10, $movement->stock_antes);
        $this->assertSame(6, $movement->stock_despues);
        $this->assertSame('spare_request', $movement->referencia_tipo);
        $this->assertSame((string) $request->id, $movement->referencia_id);
        $this->assertSame(2, Log::whereIn('accion', [
            'ajustar_inventario_por_eliminacion',
            'aprobar_eliminacion',
        ])->count());
    }

    public function test_new_inventory_forms_record_entry_movements(): void
    {
        $user = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($user)->test(InventoryIngreso::class)
            ->set('sigicov', '2001')
            ->set('descripcion', 'Repuesto ingreso')
            ->set('pasillo', 'A')
            ->set('estante', '1')
            ->set('peldaño', '1')
            ->set('resp_accesorios', 'Bodega')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($user)->test(InventoryRegister::class)
            ->set('sigicov', '2002')
            ->set('descripcion', 'Repuesto registro')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, InventoryMovement::where('tipo', 'entrada')->count());
        $this->assertSame([1, 1], InventoryMovement::query()->orderBy('delta')->pluck('delta')->all());
        $this->assertSame('1', InventoryItem::where('sigicov', '2001')->value('inventario'));
        $this->assertSame('1', InventoryItem::where('sigicov', '2002')->value('inventario'));
        $this->assertSame(2, InventoryItem::count());
    }

    public function test_backfill_is_idempotent_and_skips_zero_stock_items(): void
    {
        $normal = $this->createItem('3001', '5');
        $zero = $this->createItem('3002', '0');
        $initial = $this->createItem('3003', '8', 'inventario_inicial');

        $this->assertSame(0, Artisan::call('inventory:backfill-movements', ['--dry-run' => true]));
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, Artisan::call('inventory:backfill-movements'));
        $this->assertSame(0, Artisan::call('inventory:backfill-movements'));

        $this->assertSame(2, InventoryMovement::count());
        $this->assertSame('saldo_inicial', InventoryMovement::where('inventory_item_id', $normal->id)->value('tipo'));
        $this->assertSame('inventario_inicial', InventoryMovement::where('inventory_item_id', $initial->id)->value('tipo'));
        $this->assertFalse(InventoryMovement::where('inventory_item_id', $zero->id)->exists());
    }

    public function test_backfill_verify_detects_manual_stock_changes(): void
    {
        $item = $this->createItem('4001', '5');
        Artisan::call('inventory:backfill-movements');

        $this->assertSame(0, Artisan::call('inventory:backfill-movements', ['--verify' => true]));
        $item->update(['inventario' => '9']);
        $this->assertSame(1, Artisan::call('inventory:backfill-movements', ['--verify' => true]));
    }

    private function egreso(User $user, InventoryItem $item, Vehiculo $vehicle)
    {
        return Livewire::actingAs($user)->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id)
            ->set('vehiculo_id', $vehicle->id)
            ->set('motivo_tipo', 'preventivo')
            ->call('confirmSalida');
    }

    private function createVehicle(string $plate): Vehiculo
    {
        return Vehiculo::create([
            'placa' => $plate,
            'descripcion' => 'Camión de prueba',
        ]);
    }

    private function createItem(string $sigicov, string $stock, ?string $origin = null): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => $sigicov,
            'descripcion' => 'Repuesto de prueba',
            'inventario' => $stock,
            'origen' => $origin,
        ]);
    }
}
