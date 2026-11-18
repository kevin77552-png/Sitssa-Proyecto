<?php

namespace Tests\Feature;

use App\Livewire\Admin\Units;
use App\Livewire\DynamicModule;
use App\Livewire\Sidebar;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Log;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\UnitsSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_units_and_units_are_not_deleted_on_deactivation(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        Livewire::actingAs($warehouse)->test(Units::class)->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrador']);
        Livewire::actingAs($admin)->test(Units::class, ['section' => 'registro'])
            ->set('placa', 'ABC-123')
            ->set('externalId', 'fleet-1')
            ->set('tipo', 'BUS')
            ->set('modelo', 'M1')
            ->set('descripcion', 'Bus principal')
            ->call('save')
            ->assertHasNoErrors();

        $unit = Vehiculo::query()->where('placa', 'ABC-123')->firstOrFail();
        $this->assertSame('fleet-1', $unit->external_id);
        Livewire::actingAs($admin)->test(Units::class)
            ->call('toggleActive', $unit->id)
            ->assertSee('Unidad desactivada.');

        $this->assertSame('inactiva', $unit->fresh()->estado);
        $this->assertDatabaseHas('vehiculos', ['id' => $unit->id]);
        $this->assertDatabaseHas('logs', ['accion' => 'crear_unidad', 'entidad_id' => $unit->id]);
        $this->assertDatabaseHas('logs', ['accion' => 'cambiar_estado_unidad', 'entidad_id' => $unit->id]);
    }

    public function test_units_module_is_integrated_and_restricted_to_administrators(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        Livewire::actingAs($admin)->test(Sidebar::class)
            ->assertSee('Unidades')
            ->assertSee('Registro')
            ->assertSee('Consulta');
        Livewire::actingAs($admin)->test(DynamicModule::class)
            ->call('showModule', 'admin.units.registro')
            ->assertSee('Registrar unidad')
            ->assertDontSee('Buscar por placa');
        Livewire::actingAs($admin)->test(DynamicModule::class)
            ->call('showModule', 'admin.units.consulta')
            ->assertSee('Consulta de unidades')
            ->assertSee('Buscar por placa');

        $warehouse = User::factory()->create(['role' => 'almacenista']);
        Livewire::actingAs($warehouse)->test(Sidebar::class)
            ->assertDontSee('Unidades');
        Livewire::actingAs($warehouse)->test(DynamicModule::class)
            ->call('showModule', 'admin.units.consulta')
            ->assertForbidden();
    }

    public function test_consultation_can_open_an_existing_unit_in_registration_for_editing(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $unit = Vehiculo::create(['placa' => 'EDIT-1', 'descripcion' => 'Unidad por editar']);

        Livewire::actingAs($admin)
            ->test(Units::class, ['section' => 'consulta'])
            ->assertSee('Placa')
            ->assertSee('EDIT-1');

        Livewire::actingAs($admin)->test(DynamicModule::class)
            ->call('editUnit', $unit->id)
            ->assertSet('module', 'admin.units.registro')
            ->assertSet('unitId', $unit->id)
            ->assertSee('Editar unidad');
    }

    public function test_registration_shows_only_external_code_and_preserves_legacy_internal_code(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $unit = Vehiculo::create(['placa' => 'LEGACY-1', 'descripcion' => 'Unidad existente']);
        DB::table('vehiculos')->where('id', $unit->id)->update(['codigo_interno' => 'OLD-42']);

        Livewire::actingAs($admin)
            ->test(Units::class, ['section' => 'registro', 'editingId' => $unit->id])
            ->assertSee('Código externo')
            ->assertDontSee('Código interno')
            ->set('descripcion', 'Unidad actualizada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('OLD-42', $unit->fresh()->codigo_interno);
    }

    public function test_spreadsheet_import_plans_then_applies_unit_creations_and_updates(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $unit = Vehiculo::create(['placa' => 'OLD-1', 'descripcion' => 'Antes']);
        $file = UploadedFile::fake()->createWithContent(
            'units.csv',
            "placa,codigo_externo,tipo,modelo,descripcion,estado\nNEW-1,x-2,BUS,M2,Nuevo,activa\nOLD-1,,,,Actualizado,inactiva\n"
        );

        Livewire::actingAs($admin)->test(Units::class, ['section' => 'registro'])
            ->set('file', $file)
            ->call('analyzeImport')
            ->assertHasNoErrors()
            ->assertSee('nuevas')
            ->assertSee('por actualizar')
            ->call('applyImport')
            ->assertHasNoErrors()
            ->assertSee('Importación finalizada');

        $this->assertDatabaseHas('vehiculos', ['placa' => 'NEW-1', 'external_id' => 'x-2', 'estado' => 'activa']);
        $this->assertSame('Actualizado', $unit->fresh()->descripcion);
        $this->assertSame('inactiva', $unit->fresh()->estado);
        $this->assertDatabaseHas('logs', ['accion' => 'importar_unidades']);
    }

    public function test_unit_history_displays_linked_exit_movements(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $unit = Vehiculo::create(['placa' => 'HIST-1', 'descripcion' => 'Unidad de historial']);
        $item = InventoryItem::create(['sigicov' => 'HIST-ITEM', 'descripcion' => 'Filtro de prueba']);
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'tipo' => 'salida',
            'delta' => -1,
            'stock_antes' => 1,
            'stock_despues' => 0,
            'vehiculo_id' => $unit->id,
            'vehiculo_placa' => $unit->placa,
            'vehiculo_descripcion' => $unit->descripcion,
            'motivo' => 'Mantenimiento',
            'occurred_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(Units::class, ['section' => 'consulta'])
            ->call('showHistory', $unit->id)
            ->assertSee('HIST-1')
            ->assertSee('HIST-ITEM')
            ->assertSee('Mantenimiento');
    }

    public function test_units_spreadsheet_template_is_a_valid_xlsx_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'units-template-');
        try {
            app(UnitsSpreadsheet::class)->template($path);
            $rows = app(UnitsSpreadsheet::class)->read($path, 'xlsx');
            $this->assertSame([], $rows);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_csv_export_round_trips_and_escapes_formula_values(): void
    {
        Vehiculo::create([
            'placa' => 'CSV-1',
            'descripcion' => '=SUM(1,1)',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'units-export-');

        try {
            app(UnitsSpreadsheet::class)->exportCsv($path);
            $rows = app(UnitsSpreadsheet::class)->read($path, 'csv');
            $this->assertSame("'=SUM(1,1)", $rows[0]['descripcion']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
