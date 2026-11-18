<?php

namespace Tests\Feature;

use App\Livewire\Admin\InventoryConsulta;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryType;
use App\Models\SpareRequest;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class InventoryConsultaTest extends TestCase
{
    use RefreshDatabase;

    public function test_almacenista_sees_only_operational_inventory_columns_and_missing_date_fallback(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'peldaño' => 'B',
            'inventario' => 3,
            'fecha' => null,
            'lote_importacion' => 'lote-interno',
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('NO DISPONIBLE')
            ->assertDontSee('CANTIDAD DISPONIBLE')
            ->assertDontSee('TOTAL MES')
            ->assertDontSee('LOTE')
            ->assertDontSee('lote-interno');
    }

    public function test_consulta_filters_by_description_and_peldano(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'peldaño' => 'B',
            'inventario' => 1,
        ]);
        InventoryItem::create([
            'sigicov' => '4500130',
            'descripcion' => 'Filtro de aceite',
            'peldaño' => 'C',
            'inventario' => 1,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->set('search_descripcion', 'Luz')
            ->assertSee('4500129')
            ->assertDontSee('4500130')
            ->set('search_descripcion', '')
            ->set('search', 'luz')
            ->assertSee('4500129')
            ->assertDontSee('4500130')
            ->set('search', '')
            ->set('search_descripcion', '')
            ->set('search_peldano', 'C')
            ->assertSee('4500130')
            ->assertDontSee('4500129');
    }

    public function test_consulta_shows_stock_totals_by_type_and_filters_from_the_totals(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
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

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('aria-label="Todos los tipos: 10 unidades"', false)
            ->assertSee('aria-label="Frenos: 5 unidades"', false)
            ->assertSee('aria-label="Filtros: 4 unidades"', false)
            ->assertSee('aria-label="Sin tipo asignado: 1 unidad"', false)
            ->set('typeFilter', (string) $brakes->id)
            ->assertSet('typeFilter', (string) $brakes->id)
            ->assertSee('Disco de freno')
            ->assertDontSee('Filtro de aceite');
    }

    public function test_consulta_alerts_when_aggregated_active_stock_for_a_type_is_below_ten(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $filters = InventoryType::create(['name' => 'Tipo de prueba bajo']);
        $brakes = InventoryType::create(['name' => 'Tipo de prueba normal']);
        InventoryItem::create([
            'sigicov' => '2051',
            'descripcion' => 'Filtro de aceite',
            'inventory_type_id' => $filters->id,
            'inventario' => 4,
        ]);
        InventoryItem::create([
            'sigicov' => '2052',
            'descripcion' => 'Filtro de aire',
            'inventory_type_id' => $filters->id,
            'inventario' => 5,
        ]);
        InventoryItem::create([
            'sigicov' => '2053',
            'descripcion' => 'Disco de freno',
            'inventory_type_id' => $brakes->id,
            'inventario' => 10,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('Baja existencia por tipo')
            ->assertSeeHtml('data-low-stock-type="'.$filters->id.'"')
            ->assertDontSeeHtml('data-low-stock-type="'.$brakes->id.'"');
    }

    public function test_consulta_shows_unit_exit_movements_instead_of_deleted_items(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $active = InventoryItem::create([
            'sigicov' => '2061',
            'descripcion' => 'Repuesto activo',
            'inventario' => 1,
        ]);
        $exited = InventoryItem::create([
            'sigicov' => '2062',
            'descripcion' => 'Repuesto entregado a unidad',
            'inventario' => 0,
        ]);
        $exited->delete();
        $deletedWithoutExit = InventoryItem::create([
            'sigicov' => '2063',
            'descripcion' => 'Repuesto eliminado sin salida',
            'inventario' => 0,
        ]);
        $deletedWithoutExit->delete();
        $vehicle = Vehiculo::create([
            'placa' => 'ABC-2062',
            'descripcion' => 'Camión de prueba',
        ]);
        InventoryMovement::create([
            'inventory_item_id' => $exited->id,
            'tipo' => 'salida',
            'delta' => -1,
            'stock_antes' => 1,
            'stock_despues' => 0,
            'vehiculo_id' => $vehicle->id,
            'vehiculo_placa' => $vehicle->placa,
            'vehiculo_descripcion' => $vehicle->descripcion,
            'entregado_a' => 'Persona receptora',
            'actor_name' => $almacenista->name,
            'motivo' => 'Mantenimiento',
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee($active->sigicov)
            ->assertSee('Salidas a unidades')
            ->assertDontSee('Eliminados')
            ->call('showUnitExits')
            ->assertSet('showExits', true)
            ->assertSee($exited->sigicov)
            ->assertDontSee($active->sigicov)
            ->assertDontSee($deletedWithoutExit->sigicov)
            ->assertSee($vehicle->placa)
            ->assertSee('Persona receptora')
            ->assertSee('Mantenimiento')
            ->assertSee('Unidades')
            ->assertDontSee('Fecha de baja')
            ->call('showActiveInventory')
            ->assertSet('showExits', false)
            ->assertSee($active->sigicov)
            ->assertDontSee($exited->sigicov)
            ->assertDontSee($deletedWithoutExit->sigicov);
    }

    public function test_export_from_unit_exits_contains_delivery_details(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $item = InventoryItem::create([
            'sigicov' => '2064',
            'descripcion' => 'Repuesto para exportación',
            'inventario' => 1,
        ]);
        $vehicle = Vehiculo::create([
            'placa' => 'ABC-2064',
            'descripcion' => 'Unidad de exportación',
        ]);
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'tipo' => 'salida',
            'delta' => -1,
            'stock_antes' => 1,
            'stock_despues' => 0,
            'vehiculo_id' => $vehicle->id,
            'vehiculo_placa' => $vehicle->placa,
            'vehiculo_descripcion' => $vehicle->descripcion,
            'entregado_a' => 'Receptor de exportación',
        ]);

        $component = Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->call('showUnitExits')
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

        $this->assertStringContainsString('UNIDADES', $csv);
        $this->assertStringContainsString('ABC-2064', $csv);
        $this->assertStringContainsString('Receptor de exportación', $csv);
    }

    public function test_consulta_can_show_a_code128_barcode_for_an_inventory_item(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $item = InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'inventario' => 3,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('Ver código de barras')
            ->call('openBarcodeModal', $item->id)
            ->assertSee('Código de barras del repuesto')
            ->assertSee('Code 128')
            ->assertSee('4500129')
            ->assertSee('window.renderInventoryBarcode', false)
            ->assertSee('window.downloadInventoryBarcodeLabel', false)
            ->assertSee('Descargar etiqueta SVG')
            ->call('closeBarcodeModal')
            ->assertDontSee('Código de barras del repuesto');
    }

    public function test_selected_inventory_items_can_be_opened_as_printable_barcode_labels(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $item = InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'inventario' => 3,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->call('toggleCurrentPageSelection')
            ->assertSet('selectedBarcodeItems', [(string) $item->id])
            ->call('printSelectedBarcodes')
            ->assertDispatched('inventory-barcode-print-ready');

        $this->withoutVite()
            ->actingAs($almacenista)
            ->get(route('inventory.barcodes.print', ['ids' => (string) $item->id]))
            ->assertOk()
            ->assertSee('A4')
            ->assertSee('21 por hoja')
            ->assertSee('Luz de cabina')
            ->assertSee('data-sigicov="4500129"', false);
    }

    public function test_only_inventory_roles_can_open_printable_barcode_labels(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);
        $item = InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'inventario' => 3,
        ]);

        $this->actingAs($provider)
            ->get(route('inventory.barcodes.print', ['ids' => (string) $item->id]))
            ->assertForbidden();
    }

    public function test_almacenista_can_report_an_inventory_deletion_without_decrementing_stock(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $item = InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Disco de embrague',
            'inventario' => 3,
        ]);
        InventoryItem::create([
            'sigicov' => '4500130',
            'descripcion' => 'Repuesto sin existencias',
            'inventario' => 0,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSeeHtml('data-action="report-inventory-deletion"')
            ->assertSee('Sin existencias')
            ->call('openDeletionModal', $item->id)
            ->assertSee('ni descuenta existencias')
            ->assertSee('Este reporte corresponde a una unidad.')
            ->assertDontSee('Cantidad afectada')
            ->assertDontSeeHtml('id="deletionQuantity"')
            ->set('deletionReason', 'Pieza dañada')
            ->call('submitDeletionRequest')
            ->assertHasNoErrors()
            ->assertSet('showDeletionModal', false)
            ->assertSee('Reporte enviado al gerente para revisión');

        $request = SpareRequest::query()->where('inventory_item_id', $item->id)->firstOrFail();
        $this->assertSame('eliminacion', $request->tipo);
        $this->assertSame(1, $request->cantidad);
        $this->assertSame('pendiente', $request->status);
        $this->assertSame('3', $item->fresh()->inventario);
    }

    public function test_almacenista_cannot_report_a_deletion_when_there_is_no_stock(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $item = InventoryItem::create([
            'sigicov' => '4500130',
            'descripcion' => 'Repuesto sin existencias',
            'inventario' => 0,
        ]);

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('Sin existencias')
            ->assertDontSeeHtml('data-action="report-inventory-deletion"')
            ->call('openDeletionModal', $item->id)
            ->set('deletionReason', 'Repuesto obsoleto')
            ->call('submitDeletionRequest')
            ->assertHasErrors(['deletionReason']);

        $this->assertSame(0, SpareRequest::where('inventory_item_id', $item->id)->count());
    }

    public function test_consulta_paginates_twenty_items_and_resets_to_first_page_when_filters_change(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        foreach (range(1, 21) as $number) {
            InventoryItem::create([
                'sigicov' => sprintf('SIG-%03d', $number),
                'descripcion' => 'Repuesto '.$number,
                'inventario' => 1,
            ]);
        }

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
            ->assertSee('SIG-021')
            ->assertDontSee('SIG-001')
            ->call('nextPage')
            ->assertSee('SIG-001')
            ->assertDontSee('SIG-021')
            ->set('search', 'SIG-001')
            ->assertSee('SIG-001');
    }

    public function test_consulta_pagination_allows_changing_page_size_and_jumping_to_a_valid_page(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        foreach (range(1, 21) as $number) {
            InventoryItem::create([
                'sigicov' => sprintf('SIG-%03d', $number),
                'descripcion' => 'Repuesto '.$number,
                'inventario' => 1,
            ]);
        }

        Livewire::actingAs($almacenista)
            ->test(InventoryConsulta::class)
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

    public function test_administrators_keep_inventory_totals_and_import_lot_columns(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'inventario' => 1,
            'lote_importacion' => 'lote-123',
        ]);

        Livewire::actingAs($administrator)
            ->test(InventoryConsulta::class)
            ->assertSee('Disponible / total mes')
            ->assertSee('Origen / lote')
            ->assertSee('lote-123');
    }

    public function test_export_respects_role_specific_columns(): void
    {
        $almacenista = User::factory()->create(['role' => 'almacenista']);
        $administrator = User::factory()->create(['role' => 'administrador']);
        InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => 'Luz de cabina',
            'inventario' => 1,
            'lote_importacion' => 'lote-123',
        ]);

        $warehouseCsv = $this->exportCsv($almacenista);
        $this->assertStringNotContainsString('CANTIDAD_DISPONIBLE', $warehouseCsv);
        $this->assertStringNotContainsString('TOTAL_MES', $warehouseCsv);
        $this->assertStringNotContainsString('LOTE_IMPORTACION', $warehouseCsv);
        $this->assertStringNotContainsString('lote-123', $warehouseCsv);

        $adminCsv = $this->exportCsv($administrator);
        $this->assertStringContainsString('CANTIDAD_DISPONIBLE', $adminCsv);
        $this->assertStringContainsString('TOTAL_MES', $adminCsv);
        $this->assertStringContainsString('LOTE_IMPORTACION', $adminCsv);
        $this->assertStringContainsString('lote-123', $adminCsv);
    }

    public function test_xlsx_inventory_export_excludes_items_with_no_physical_stock(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        InventoryItem::create([
            'sigicov' => 'IN-STOCK',
            'descripcion' => 'Repuesto disponible',
            'inventario' => 2,
        ]);
        $exhausted = InventoryItem::create([
            'sigicov' => 'ASSIGNED-OUT',
            'descripcion' => 'Repuesto entregado a unidad',
            'inventario' => 0,
        ]);
        $vehicle = Vehiculo::create(['placa' => 'ABC-123', 'descripcion' => 'Unidad de prueba']);
        InventoryMovement::create([
            'inventory_item_id' => $exhausted->id,
            'tipo' => 'salida',
            'delta' => -1,
            'stock_antes' => 1,
            'stock_despues' => 0,
            'vehiculo_id' => $vehicle->id,
            'vehiculo_placa' => $vehicle->placa,
            'vehiculo_descripcion' => $vehicle->descripcion,
        ]);

        $component = Livewire::actingAs($administrator)
            ->test(InventoryConsulta::class)
            ->set('exportFormat', 'xlsx')
            ->instance();
        $response = $component->export();
        ob_start();
        try {
            $response->sendContent();
            $xlsx = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $path = tempnam(sys_get_temp_dir(), 'inventory-stock-export-');
        file_put_contents($path, $xlsx);
        $spreadsheet = null;
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('IN-STOCK', $sheet->getCell('A2')->getValue());
            $this->assertNull($sheet->getCell('A3')->getValue());
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_export_treats_formula_like_inventory_values_as_text(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $description = " \t=HYPERLINK(\"HTTPS://EXAMPLE.TEST\")";
        InventoryItem::create([
            'sigicov' => '4500129',
            'descripcion' => $description,
            'inventario' => 1,
        ]);

        $csv = $this->exportCsv($administrator);
        $csvRows = preg_split('/\r\n|\n|\r/', $csv);
        $this->assertSame("'".$description, str_getcsv($csvRows[1])[1]);

        $component = Livewire::actingAs($administrator)
            ->test(InventoryConsulta::class)
            ->set('exportFormat', 'xlsx')
            ->instance();
        $response = $component->export();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        try {
            $response->sendContent();
            $xlsx = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $path = tempnam(sys_get_temp_dir(), 'inventory-export-');
        file_put_contents($path, $xlsx);
        $spreadsheet = null;
        try {
            $spreadsheet = IOFactory::load($path);
            $cell = $spreadsheet->getActiveSheet()->getCell('B2');
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame($description, $cell->getValue());
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unlink($path);
        }
    }

    private function exportCsv(User $user): string
    {
        $component = Livewire::actingAs($user)
            ->test(InventoryConsulta::class)
            ->set('exportFormat', 'csv')
            ->instance();

        ob_start();
        try {
            $response = $component->export();
            $this->assertInstanceOf(StreamedResponse::class, $response);
            $response->sendContent();

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
