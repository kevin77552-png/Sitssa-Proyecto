<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryEgreso;
use App\Livewire\Admin\InventoryConsulta;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryType;
use App\Models\Log;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\InventoryMovementRecorder;
use Database\Seeders\FleetUnitsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EgresoPorUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
        InventoryMovementRecorder::resetCache();
        $this->seed(FleetUnitsDemoSeeder::class);
    }

    private function almacenista(): User
    {
        return User::factory()->create(['role' => 'almacenista']);
    }

    private function item(string $stock = '5'): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => '1001',
            'descripcion' => 'FILTRO DE ACEITE',
            'inventario' => $stock,
        ]);
    }

    private function unidad(string $placa = 'ABC101'): Vehiculo
    {
        return Vehiculo::where('placa', $placa)->firstOrFail();
    }

    private function salida(InventoryItem $item, array $fields)
    {
        $test = Livewire::actingAs($this->almacenista())
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id);

        foreach (array_merge(['entregadoA' => 'Persona de prueba'], $fields) as $field => $value) {
            $test->set($field, $value);
        }

        return $test->call('confirmSalida');
    }

    public function test_exit_decrements_one_unit_and_records_vehicle_and_reason(): void
    {
        $item = $this->item();
        $vehicle = $this->unidad();

        $this->salida($item, [
            'vehiculo_id' => $vehicle->id,
            'motivo_tipo' => 'preventivo',
        ])->assertHasNoErrors();

        $this->assertSame('4', $item->fresh()->inventario);
        $movement = InventoryMovement::firstOrFail();
        $this->assertSame(-1, $movement->delta);
        $this->assertSame($vehicle->id, $movement->vehiculo_id);
        $this->assertSame('ABC101', $movement->vehiculo_placa);
        $this->assertSame('preventivo', $movement->motivo_tipo);
        $this->assertSame('Persona de prueba', $movement->entregado_a);
        $this->assertTrue(Log::where('accion', 'salida')->where('entidad_id', $item->id)->exists());
    }

    public function test_exit_that_uses_the_last_unit_archives_item_and_shows_it_in_deleted_inventory(): void
    {
        $user = $this->almacenista();
        $item = $this->item('1');
        $vehicle = $this->unidad();

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id)
            ->set('item_id', $item->id)
            ->set('entregadoA', 'Persona de prueba')
            ->set('vehiculo_id', $vehicle->id)
            ->set('motivo_tipo', 'preventivo')
            ->call('confirmSalida')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
        $this->assertSame('0', InventoryItem::withTrashed()->findOrFail($item->id)->inventario);
        $this->assertSame(0, InventoryItem::query()->count());
        $this->assertSame(0, (int) InventoryMovement::where('inventory_item_id', $item->id)->value('stock_despues'));

        Livewire::actingAs($user)
            ->test(InventoryConsulta::class)
            ->assertDontSee('1001')
            ->call('showDeletedInventory')
            ->assertSee('1001')
            ->assertSee('Archivado')
            ->assertSee('Fecha de baja');
    }

    public function test_a_vehicle_must_be_selected_from_the_catalog(): void
    {
        $item = $this->item();

        $this->salida($item, ['motivo_tipo' => 'correctivo'])
            ->assertHasErrors(['vehiculo_id']);

        $this->assertSame('5', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_recipient_is_required_for_an_exit(): void
    {
        $item = $this->item();

        $this->salida($item, [
            'vehiculo_id' => $this->unidad()->id,
            'entregadoA' => ' ',
            'motivo_tipo' => 'preventivo',
        ])->assertHasErrors(['entregadoA']);

        $this->assertSame('5', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_inactive_and_nonexistent_vehicles_are_rejected(): void
    {
        $item = $this->item();

        $this->salida($item, [
            'vehiculo_id' => $this->unidad('ABC116')->id,
            'motivo_tipo' => 'preventivo',
        ])->assertHasErrors(['vehiculo_id']);
        $this->salida($item, [
            'vehiculo_id' => 99999,
            'motivo_tipo' => 'preventivo',
        ])->assertHasErrors(['vehiculo_id']);

        $this->assertSame('5', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_reason_is_required_and_other_requires_an_explanation(): void
    {
        $item = $this->item();
        $vehicle = $this->unidad();

        $this->salida($item, ['vehiculo_id' => $vehicle->id])
            ->assertHasErrors(['motivo_tipo']);
        $this->salida($item, [
            'vehiculo_id' => $vehicle->id,
            'motivo_tipo' => 'otro',
        ])->assertHasErrors(['motivo']);
        $this->salida($item, [
            'vehiculo_id' => $vehicle->id,
            'motivo_tipo' => 'otro',
            'motivo' => 'Préstamo a taller externo',
        ])->assertHasNoErrors();

        $this->assertSame('Préstamo a taller externo', InventoryMovement::firstOrFail()->motivo);
    }

    public function test_no_stock_does_not_create_movement_or_audit_log(): void
    {
        $item = $this->item('0');

        $this->salida($item, [
            'vehiculo_id' => $this->unidad()->id,
            'motivo_tipo' => 'preventivo',
        ])->assertSet('error', 'No hay existencias disponibles para registrar esta entrega.');

        $this->assertSame('0', $item->fresh()->inventario);
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, Log::where('accion', 'salida')->count());
    }

    public function test_selector_contains_only_active_units_and_search_metadata(): void
    {
        Livewire::actingAs($this->almacenista())
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $this->item()->id)
            ->assertSee('ABC101')
            ->assertSee('12 MTS')
            ->assertSee('ZK6118HGA')
            ->assertDontSee('ABC116')
            ->assertSee('Busca por placa, tipo o modelo')
            ->assertSee('Motivo de la entrega')
            ->assertSee('Entregado a')
            ->assertSee('Se descontará')
            ->assertDontSee('Cantidad a retirar')
            ->assertDontSee('Placa del vehículo')
            ->assertDontSee('Descripción del vehículo');
    }

    public function test_egress_screen_has_one_link_to_all_exit_movements(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista', 'name' => 'Almacenista Demo']);
        Livewire::actingAs($warehouse)
            ->test(InventoryEgreso::class)
            ->assertSee('Ver todas las salidas')
            ->assertSee('tipo=salida')
            ->assertSee('allTime=1')
            ->assertDontSee('showExitHistory');
    }

    public function test_egress_shows_available_stock_totals_and_filters_by_type(): void
    {
        $brakes = InventoryType::create(['name' => 'Frenos']);
        $filters = InventoryType::create(['name' => 'Filtros']);
        InventoryItem::create([
            'sigicov' => '2001',
            'descripcion' => 'Disco de freno',
            'inventory_type_id' => $brakes->id,
            'inventario' => '3',
        ]);
        InventoryItem::create([
            'sigicov' => '2002',
            'descripcion' => 'Pastillas',
            'inventory_type_id' => $brakes->id,
            'inventario' => '2',
        ]);
        InventoryItem::create([
            'sigicov' => '2003',
            'descripcion' => 'Filtro de aceite',
            'inventory_type_id' => $filters->id,
            'inventario' => '4',
        ]);
        InventoryItem::create([
            'sigicov' => '2004',
            'descripcion' => 'Repuesto sin clasificar',
            'inventario' => '1',
        ]);

        Livewire::actingAs($this->almacenista())
            ->test(InventoryEgreso::class)
            ->assertSee('aria-label="Todos los tipos: 10 unidades"', false)
            ->assertSee('aria-label="Frenos: 5 unidades"', false)
            ->assertSee('aria-label="Filtros: 4 unidades"', false)
            ->assertSee('aria-label="Sin tipo asignado: 1 unidad"', false)
            ->set('typeFilter', (string) $brakes->id)
            ->assertSet('typeFilter', (string) $brakes->id)
            ->assertSee('Disco de freno')
            ->assertDontSee('Filtro de aceite');
    }

    public function test_inventory_and_egress_use_the_horizontal_type_filter_without_a_dropdown(): void
    {
        $user = $this->almacenista();

        Livewire::actingAs($user)
            ->test(InventoryConsulta::class)
            ->assertDontSee('inventory-type-filter')
            ->assertSee('Desliza horizontalmente para ver todos los tipos');

        Livewire::actingAs($user)
            ->test(InventoryEgreso::class)
            ->assertDontSee('egreso-type-filter')
            ->assertSee('Desliza horizontalmente para ver todos los tipos');
    }

    public function test_egress_can_show_a_code128_barcode_for_an_inventory_item(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->almacenista())
            ->test(InventoryEgreso::class)
            ->assertSee('Ver código')
            ->call('openBarcodeModal', $item->id)
            ->assertSee('Código de barras del repuesto')
            ->assertSee('Code 128')
            ->assertSee('1001')
            ->assertSee('window.renderInventoryBarcode', false)
            ->assertSee('window.downloadInventoryBarcodeLabel', false)
            ->assertSee('Descargar etiqueta SVG')
            ->call('closeBarcodeModal')
            ->assertDontSee('Código de barras del repuesto');
    }

    public function test_demo_seeder_is_idempotent_and_marks_one_unit_inactive(): void
    {
        $this->seed(FleetUnitsDemoSeeder::class);

        $this->assertSame(16, Vehiculo::count());
        $this->assertSame(15, Vehiculo::activas()->count());
        $this->assertSame(16, Vehiculo::where('external_id', 'like', 'DEMO-%')->count());
    }

    public function test_reopening_exit_modal_starts_with_a_clean_form(): void
    {
        $warehouseUser = $this->almacenista();
        $item = $this->item();
        $component = Livewire::actingAs($warehouseUser)
            ->test(InventoryEgreso::class)
            ->call('openSalidaModal', $item->id)
            ->set('vehiculo_id', $this->unidad()->id)
            ->set('motivo_tipo', 'siniestro')
            ->set('motivo', 'Observación de prueba')
            ->call('closeSalidaModal')
            ->call('openSalidaModal', $item->id);

        $component
            ->assertSet('vehiculo_id', '')
            ->assertSet('entregadoA', '')
            ->assertSet('motivo_tipo', '')
            ->assertSet('motivo', '')
            ->assertSee('Elige la unidad y el motivo');
    }
}
