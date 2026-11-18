<?php

namespace Tests\Feature;

use App\Livewire\Admin\Migration;
use App\Livewire\Admin\Reports;
use App\Livewire\DynamicModule;
use App\Livewire\Wiki\Ayuda;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AdminPresentationViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_render_cards_and_empty_states(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Reports::class)
            ->assertSee('Solicitudes pendientes')
            ->assertSee('Exportar CSV')
            ->assertSee('Rechazos frecuentes (solicitudes)')
            ->assertSee('Sin solicitudes pendientes.');
    }

    public function test_operational_report_can_be_exported_as_csv(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Reports::class)
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_migration_view_keeps_export_and_links_to_staged_import(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        $this->assertFalse(Schema::hasTable('inventory_import_batches'));
        $this->assertFalse(Schema::hasTable('inventory_import_rows'));
        $this->assertFalse(Schema::hasTable('inventory_import_chunks'));
        $this->assertTrue(Schema::hasTable('import_batches'));
        $this->assertTrue(Schema::hasTable('import_rows'));

        Livewire::actingAs($administrator)
            ->test(Migration::class)
            ->assertSee('Descargar CSV')
            ->assertSee('CSV')
            ->assertSee('XLSX')
            ->assertDontSee('PDF')
            ->assertSee('Importar archivo')
            ->assertDontSee('Revisión de importación');
    }

    public function test_inventory_export_shows_real_scope_counts_and_supports_csv_and_xlsx(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $availableItem = \App\Models\InventoryItem::create([
            'sigicov' => 'EX-001',
            'descripcion' => 'Repuesto disponible',
            'inventario' => '4',
            'pasillo' => 'A',
            'estante' => '1',
            'peldaño' => '2',
        ]);
        \App\Models\InventoryItem::create([
            'sigicov' => 'EX-002',
            'descripcion' => 'Repuesto sin ubicación',
            'inventario' => '0',
        ]);
        \App\Models\InventoryMovement::create([
            'inventory_item_id' => $availableItem->id,
            'tipo' => 'salida',
            'delta' => -1,
            'stock_antes' => 5,
            'stock_despues' => 4,
        ]);

        Livewire::actingAs($administrator)
            ->test(Migration::class)
            ->assertSee('2 registros totales')
            ->set('scope', 'available')
            ->assertSee('1 fila incluida')
            ->set('scope', 'incomplete')
            ->assertSee('1 fila incluida')
            ->set('scope', 'dispatched')
            ->assertSee('1 fila incluida')
            ->call('export')
            ->assertFileDownloaded();

        Livewire::actingAs($administrator)
            ->test(Migration::class)
            ->set('exportFormat', 'xlsx')
            ->set('scope', 'available')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_migration_export_treats_formula_like_inventory_values_as_text(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $description = " \t=HYPERLINK(\"HTTPS://EXAMPLE.TEST\")";
        InventoryItem::create([
            'sigicov' => 'EX-003',
            'descripcion' => $description,
            'inventario' => '1',
        ]);

        $component = Livewire::actingAs($administrator)
            ->test(Migration::class)
            ->set('exportFormat', 'csv')
            ->instance();
        $csvResponse = $component->export();
        ob_start();
        try {
            $csvResponse->sendContent();
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $csvRows = preg_split('/\r\n|\n|\r/', $csv);
        $this->assertSame("'".$description, str_getcsv($csvRows[1])[2]);

        $component = Livewire::actingAs($administrator)
            ->test(Migration::class)
            ->set('exportFormat', 'xlsx')
            ->instance();
        $xlsxResponse = $component->export();
        $path = $xlsxResponse->getFile()->getPathname();
        $spreadsheet = null;
        try {
            $spreadsheet = IOFactory::load($path);
            $cell = $spreadsheet->getActiveSheet()->getCell('C2');
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame($description, $cell->getValue());
        } finally {
            $spreadsheet?->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_module_is_labeled_as_exportation(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(DynamicModule::class)
            ->call('showModule', 'admin.migration')
            ->assertSee('Exportación de datos')
            ->assertDontSee('Migración de datos');
    }

    public function test_help_view_uses_search_and_role_specific_open_state(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(Ayuda::class)
            ->assertSee('¿Qué necesitas hacer?')
            ->assertSee('supervisor_account')
            ->assertSee('Almacenista (Gestión Diaria del Inventario)');
    }
}
