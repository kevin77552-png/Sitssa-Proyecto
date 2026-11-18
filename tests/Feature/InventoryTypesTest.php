<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryConsulta;
use App\Livewire\Admin\InventoryEgreso;
use App\Livewire\Admin\InventoryIngreso;
use App\Livewire\Admin\Migration;
use App\Livewire\Admin\InventoryTypes;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class InventoryTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_the_catalog_but_warehouse_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        Livewire::actingAs($admin)
            ->test(InventoryTypes::class)
            ->set('name', 'Filtros')
            ->set('keywordsText', "filtro, aceite\nlubricante")
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Filtros');

        $type = InventoryType::query()->where('name', 'Filtros')->sole();
        $this->assertSame(['filtro', 'aceite', 'lubricante'], $type->keywords);

        $warehouse = User::factory()->create(['role' => 'almacenista']);
        Livewire::actingAs($warehouse)->test(InventoryTypes::class)->assertForbidden();
    }

    public function test_deactivating_a_type_preserves_assignments_and_unassigned_items(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $type = InventoryType::create(['name' => 'Filtros']);
        $assignedItem = InventoryItem::create([
            'sigicov' => '905',
            'descripcion' => 'Filtro asignado',
            'inventory_type_id' => $type->id,
        ]);
        $unassignedItem = InventoryItem::create([
            'sigicov' => '906',
            'descripcion' => 'Repuesto sin tipo',
        ]);

        Livewire::actingAs($admin)
            ->test(InventoryTypes::class)
            ->call('toggleActive', $type->id)
            ->assertSee('Tipo desactivado; las asignaciones históricas se conservan.');

        $this->assertFalse($type->fresh()->is_active);
        $this->assertSame($type->id, $assignedItem->fresh()->inventory_type_id);
        $this->assertNull($unassignedItem->fresh()->inventory_type_id);

        Livewire::actingAs($admin)
            ->test(InventoryConsulta::class)
            ->set('typeFilter', (string) $type->id)
            ->assertSee($assignedItem->sigicov)
            ->assertSee('· inactivo')
            ->assertDontSee($unassignedItem->sigicov)
            ->set('typeFilter', 'unassigned')
            ->assertSee($unassignedItem->sigicov)
            ->assertDontSee($assignedItem->sigicov);
    }

    public function test_inventory_can_be_classified_at_entry_and_historical_items_can_be_updated_from_consulta(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $type = InventoryType::create(['name' => 'Filtros', 'keywords' => ['filtro']]);

        Livewire::actingAs($admin)
            ->test(InventoryIngreso::class)
            ->set('sigicov', '801')
            ->set('descripcion', 'Filtro de aceite')
            ->set('inventory_type_id', (string) $type->id)
            ->set('pasillo', 'A')
            ->set('estante', '1')
            ->set('peldaño', '1')
            ->set('resp_accesorios', 'Bodega')
            ->call('save')
            ->assertHasNoErrors();

        $item = InventoryItem::query()->where('sigicov', '801')->firstOrFail();
        $this->assertSame($type->id, $item->inventory_type_id);
        $this->assertSame('1', $item->inventario);

        $historical = InventoryItem::create(['sigicov' => '802', 'descripcion' => 'Repuesto histórico']);
        Livewire::actingAs($admin)
            ->test(InventoryConsulta::class)
            ->call('assignType', $historical->id, (string) $type->id)
            ->assertHasNoErrors();

        $this->assertSame($type->id, $historical->fresh()->inventory_type_id);
    }

    public function test_consulta_and_egreso_filter_by_catalog_type_and_unassigned_items(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $filters = InventoryType::create(['name' => 'Filtros']);
        $lights = InventoryType::query()->where('name', 'Iluminación')->sole();
        InventoryItem::create(['sigicov' => '901', 'descripcion' => 'Filtro', 'inventory_type_id' => $filters->id]);
        InventoryItem::create(['sigicov' => '902', 'descripcion' => 'Lámpara', 'inventory_type_id' => $lights->id]);
        InventoryItem::create(['sigicov' => '903', 'descripcion' => 'Histórico']);

        Livewire::actingAs($warehouse)
            ->test(InventoryConsulta::class)
            ->set('typeFilter', (string) $filters->id)
            ->assertSee('901')
            ->assertDontSee('902')
            ->set('typeFilter', 'unassigned')
            ->assertSee('903')
            ->assertDontSee('901');

        Livewire::actingAs($warehouse)
            ->test(InventoryEgreso::class)
            ->set('typeFilter', (string) $lights->id)
            ->assertSee('902')
            ->assertDontSee('901')
            ->set('typeFilter', 'unassigned')
            ->assertSee('903')
            ->assertDontSee('902');
    }

    public function test_recommended_catalog_includes_lighting_and_other_vehicle_part_types(): void
    {
        $this->assertDatabaseHas('inventory_types', ['name' => 'Iluminación']);
        $this->assertDatabaseHas('inventory_types', ['name' => 'Motor']);
        $this->assertDatabaseHas('inventory_types', ['name' => 'Frenos']);

        $warehouse = User::factory()->create(['role' => 'almacenista']);
        Livewire::actingAs($warehouse)
            ->test(InventoryIngreso::class)
            ->set('descripcion', 'Faro delantero')
            ->assertSee('Sugerir tipo')
            ->assertDontSee('Usar sugerencia: Iluminación')
            ->call('suggestType')
            ->assertSee('Sugerencia detectada:')
            ->assertSee('Usar sugerencia: Iluminación')
            ->assertSee('Iluminación');
    }

    public function test_inventory_export_includes_catalog_type(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $type = InventoryType::create(['name' => 'Filtros']);
        InventoryItem::create([
            'sigicov' => '904',
            'descripcion' => 'Filtro',
            'inventory_type_id' => $type->id,
        ]);
        $component = Livewire::actingAs($admin)
            ->test(Migration::class)
            ->set('exportFormat', 'csv')
            ->instance();

        ob_start();
        try {
            $response = $component->export();
            $this->assertInstanceOf(StreamedResponse::class, $response);
            $response->sendContent();
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertStringContainsString('tipo', $csv);
        $this->assertStringContainsString('Filtros', $csv);
    }
}
