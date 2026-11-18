<?php

namespace Tests\Feature;

use App\Livewire\Admin\MovementsReport;
use App\Livewire\DynamicModule;
use App\Livewire\Sidebar;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMovementsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_movements.enabled' => true]);
    }

    public function test_report_is_available_from_dashboard_and_sidebar_for_allowed_roles(): void
    {
        foreach (['administrador', 'superadministrador', 'gerente', 'almacenista'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->withoutVite()
                ->actingAs($user)
                ->get(route('dashboard', ['module' => 'admin.movements']))
                ->assertOk()
                ->assertSee('Trazabilidad y movimientos de inventario')
                ->assertSee('Movimientos');

            Livewire::actingAs($user)
                ->test(Sidebar::class)
                ->assertSee('Movimientos');
        }
    }

    public function test_report_module_and_component_reject_provider(): void
    {
        $provider = User::factory()->create(['role' => 'proveedor']);

        $this->actingAs($provider)
            ->get(route('dashboard', ['module' => 'admin.movements']))
            ->assertForbidden();

        Livewire::actingAs($provider)
            ->test(MovementsReport::class)
            ->assertForbidden();

        Livewire::actingAs($provider)
            ->test(DynamicModule::class)
            ->call('showModule', 'admin.movements')
            ->assertForbidden();
    }

    public function test_stockkeeper_sees_inventory_movements_but_not_restricted_reports(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);

        Livewire::actingAs($warehouse)
            ->test(Sidebar::class)
            ->assertSee('Movimientos')
            ->assertDontSee('Reportes');

        $this->actingAs($warehouse)
            ->get(route('dashboard', ['module' => 'admin.reports']))
            ->assertForbidden();
    }

    public function test_report_filters_by_date_type_vehicle_and_part_and_shows_totals(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $item = $this->createItem('SIG-100', 'FILTRO PRINCIPAL');
        $otherItem = $this->createItem('SIG-200', 'FILTRO SECUNDARIO');
        $vehicle = Vehiculo::create(['placa' => 'ABC-123', 'descripcion' => 'Camión de prueba']);

        $this->createMovement($item, 'salida', -2, 10, 8, '2025-01-15 10:00:00', $vehicle, 'Mecánico Demo');
        $this->createMovement($item, 'entrada', 5, 8, 13, '2025-01-15 11:00:00');
        $this->createMovement($otherItem, 'salida', -6, 12, 6, '2025-01-15 12:00:00', $vehicle);
        $this->createMovement($item, 'salida', -3, 13, 10, '2025-02-01 09:00:00', $vehicle);

        Livewire::actingAs($admin)
            ->test(MovementsReport::class)
            ->set('from', '2025-01-01')
            ->set('to', '2025-01-31')
            ->set('tipo', 'salida')
            ->set('search', 'SIG-100')
            ->assertSee('SIG-100')
            ->assertSee('ABC-123')
            ->assertSee('Mecánico Demo')
            ->assertDontSee('SIG-200')
            ->assertSee('Entradas')
            ->assertSee('Salidas')
            ->assertSee('5')
            ->assertSee('2')
            ->set('search', 'ABC-123')
            ->assertSee('No hay movimientos que coincidan con el filtro')
            ->assertDontSee('SIG-100');
    }

    public function test_all_time_exit_report_includes_old_exits_and_orders_newest_first(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $oldItem = $this->createItem('SIG-OLD', 'REPUESTO SALIDA ANTIGUA');
        $newItem = $this->createItem('SIG-NEW', 'REPUESTO SALIDA NUEVA');
        $entryItem = $this->createItem('SIG-ENTRY', 'REPUESTO SOLO INGRESO');

        $this->createMovement($oldItem, 'salida', -1, 4, 3, '2024-01-15 10:00:00');
        $this->createMovement($newItem, 'salida', -1, 5, 4, now()->subDay()->toDateTimeString());
        $this->createMovement($entryItem, 'entrada', 2, 0, 2, now()->toDateTimeString());

        $this->withoutVite()
            ->actingAs($admin)
            ->get(route('dashboard', [
                'module' => 'admin.movements',
                'tipo' => 'salida',
                'allTime' => '1',
            ]))
            ->assertOk()
            ->assertSee('REPUESTO SALIDA NUEVA')
            ->assertSee('REPUESTO SALIDA ANTIGUA')
            ->assertDontSee('REPUESTO SOLO INGRESO')
            ->assertSeeInOrder(['REPUESTO SALIDA NUEVA', 'REPUESTO SALIDA ANTIGUA'])
            ->assertSee('Todo el historial');
    }

    public function test_report_uses_the_new_movements_design_and_functional_pagination_controls(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        foreach (range(1, 26) as $number) {
            $item = $this->createItem(sprintf('SIG-%03d', $number), 'Repuesto '.$number);
            $this->createMovement($item, 'entrada', 1, 0, 1, now()->subMinutes(30 - $number)->toDateTimeString());
        }

        Livewire::actingAs($admin)
            ->test(MovementsReport::class)
            ->assertSee('Trazabilidad y movimientos de inventario')
            ->assertSee('Exportar CSV')
            ->assertSee('Mostrar:')
            ->assertSee('Ir a pág:')
            ->assertDontSee('Simular despacho a vehículo')
            ->assertSee('SIG-026')
            ->assertDontSee('SIG-001')
            ->set('perPage', 15)
            ->assertSet('perPage', 15)
            ->set('pageJump', '2')
            ->call('jumpToPage')
            ->assertHasNoErrors()
            ->assertSee('SIG-011')
            ->assertSee('SIG-001')
            ->assertDontSee('SIG-012')
            ->set('pageJump', '3')
            ->call('jumpToPage')
            ->assertHasErrors('pageJump')
            ->assertSee('SIG-011');
    }

    public function test_csv_export_uses_active_filters_and_escapes_formula_values(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $dangerousItem = $this->createItem('SIG-300', '=HYPERLINK("https://example.test")');
        $otherItem = $this->createItem('SIG-400', 'OTRO REPUESTO');
        $this->createMovement($dangerousItem, 'entrada', 1, 0, 1, '2025-01-15 10:00:00', recipient: 'Mecánico Demo');
        $this->createMovement($otherItem, 'entrada', 2, 0, 2, '2025-01-15 10:00:00');

        Auth::login($admin);
        $report = app(MovementsReport::class);
        $report->from = '2025-01-01';
        $report->to = '2025-01-31';
        $report->search = 'SIG-300';
        $response = $report->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('filename=movimientos_inventario_', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Mecánico Demo', $csv);
        $this->assertStringNotContainsString('SIG-400', $csv);
    }

    public function test_report_explains_when_migration_is_not_applied(): void
    {
        Schema::dropIfExists('inventory_movements');
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(MovementsReport::class)
            ->assertSee('La tabla de movimientos aún no existe')
            ->assertDontSee('Exportar CSV');
    }

    public function test_report_explains_when_feature_is_disabled(): void
    {
        config(['inventory_movements.enabled' => false]);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(MovementsReport::class)
            ->assertSee('El registro de movimientos está deshabilitado')
            ->assertDontSee('Exportar CSV');
    }

    private function createItem(string $sigicov, string $description): InventoryItem
    {
        return InventoryItem::create([
            'sigicov' => $sigicov,
            'descripcion' => $description,
            'inventario' => '20',
        ]);
    }

    private function createMovement(
        InventoryItem $item,
        string $type,
        int $delta,
        int $stockBefore,
        int $stockAfter,
        string $occurredAt,
        ?Vehiculo $vehicle = null,
        ?string $recipient = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'tipo' => $type,
            'delta' => $delta,
            'stock_antes' => $stockBefore,
            'stock_despues' => $stockAfter,
            'vehiculo_id' => $vehicle?->id,
            'vehiculo_placa' => $vehicle?->placa,
            'vehiculo_descripcion' => $vehicle?->descripcion,
            'entregado_a' => $recipient,
            'occurred_at' => $occurredAt,
        ]);
    }
}
