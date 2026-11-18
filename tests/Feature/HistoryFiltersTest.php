<?php

namespace Tests\Feature;

use App\Livewire\Admin\History;
use App\Livewire\Admin\UserList;
use App\Models\InventoryItem;
use App\Models\Log;
use App\Models\SpareRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_filters_can_be_cleared(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('entityFilter', 'inventory_item')
            ->set('userFilter', '1')
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->call('clearFilters')
            ->assertSet('entityFilter', '')
            ->assertSet('userFilter', '')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '');
    }

    public function test_history_all_quick_range_clears_dates_without_resetting_other_filters(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('entityFilter', 'inventory_item')
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->call('setQuickRange', 'all')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertSet('entityFilter', 'inventory_item');
    }

    public function test_history_filters_update_results_by_entity_user_and_date_range(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $otherUser = User::factory()->create();

        $matchingLog = $this->createLog($administrator, 'inventory_item', 'IN RANGE', '2026-01-01 00:00:00');
        $matchingEndLog = $this->createLog($administrator, 'inventory_item', 'END DATE', '2026-01-31 23:59:59');
        $this->createLog($administrator, 'purchase_order', 'WRONG ENTITY', '2026-01-15 12:00:00');
        $this->createLog($otherUser, 'inventory_item', 'WRONG USER', '2026-01-15 12:00:00');
        $this->createLog($administrator, 'inventory_item', 'OUTSIDE RANGE', '2026-02-01 00:00:00');

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('entityFilter', 'inventory_item')
            ->set('userFilter', (string) $administrator->id)
            ->set('dateFrom', '2026-01-01')
            ->set('dateTo', '2026-01-31')
            ->assertSee($matchingLog->descripcion)
            ->assertSee($matchingEndLog->descripcion)
            ->assertDontSee('WRONG ENTITY')
            ->assertDontSee('WRONG USER')
            ->assertDontSee('OUTSIDE RANGE');
    }

    public function test_history_displays_soft_deleted_inventory_items_in_audit_records(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $item = InventoryItem::create([
            'sigicov' => '44009',
            'descripcion' => 'Repuesto retirado',
        ]);
        $item->delete();

        $this->createLog($administrator, 'inventory_item', 'Movimiento registrado', now(), $item->id);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('44009')
            ->assertSee('REPUESTO RETIRADO')
            ->assertDontSee('Repuesto eliminado');
    }

    public function test_history_rejects_a_date_range_that_starts_after_it_ends(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('dateFrom', '2026-01-02')
            ->set('dateTo', '2026-01-01')
            ->assertHasErrors('dateTo')
            ->assertSee('La fecha "Hasta" debe ser igual o posterior a "Desde".');
    }

    public function test_history_search_and_action_filter_match_audit_details(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $matchingLog = $this->createLog($administrator, 'inventory_item', 'Motivo: motor sin corriente', now());
        $this->createLog($administrator, 'inventory_item', 'Motivo: filtro roto', now());

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('actionFilter', 'editar')
            ->set('search', 'motor sin corriente')
            ->assertSee($matchingLog->descripcion)
            ->assertDontSee('filtro roto')
            ->assertSee('1 registro');
    }

    public function test_history_preserves_actor_and_entity_snapshot_after_records_change(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $originalName = $administrator->name;
        $request = SpareRequest::create([
            'cantidad' => 1,
            'descripcion' => 'Destornillador',
            'almacenista_id' => $administrator->id,
            'status' => 'pending',
        ]);
        $log = Log::create([
            'accion' => 'crear_solicitud',
            'entidad' => 'spare_request',
            'entidad_id' => $request->id,
            'descripcion' => 'Solicitud creada: Destornillador',
            'user_id' => $administrator->id,
        ]);

        $administrator->name = 'Nombre actualizado';
        $administrator->save();
        $request->status = 'approved';
        $request->save();

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('Solicitud creada')
            ->assertSee('Solicitud #'.$request->id.' - Destornillador')
            ->assertSee($originalName)
            ->assertDontSee('Aprobada');

        $this->assertSame($originalName, $log->actor_name);
        $this->assertSame('administrador', $log->actor_role);
    }

    public function test_history_prefers_user_name_when_snapshot_contains_only_generic_user_id(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $log = $this->createLog($administrator, 'purchase_order', 'Orden registrada', now());
        $log->actor_name = 'Usuario #'.$administrator->id;
        $log->save();

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee($administrator->name)
            ->assertDontSee('Usuario #'.$administrator->id)
            ->assertSee('Orden de compra');

        $this->assertSame('Orden de compra', Log::entityLabel('purchase_order'));
        $this->assertSame('Solicitud', Log::entityLabel('spare_request'));
    }

    public function test_history_details_modal_shows_the_complete_audit_description(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $log = $this->createLog($administrator, 'inventory_item', 'Detalle de prueba con texto completo', now());

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->call('showDetails', $log->id)
            ->assertSee('Detalle de prueba con texto completo')
            ->assertSee('role="dialog"', false)
            ->call('closeDetails')
            ->assertDontSee('role="dialog"', false);
    }

    public function test_legacy_user_edit_log_explains_when_field_changes_were_not_saved(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $log = $this->createLog($administrator, 'user', 'Edición de usuario: Kevin Mejia', now(), 2);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('Este registro no conserva los campos y valores modificados.')
            ->call('showDetails', $log->id)
            ->assertSee('no es posible reconstruir exactamente qué se modificó.');
    }

    public function test_history_humanizes_legacy_import_reversal_logs_without_showing_server_path(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $backupPath = 'C:\\Users\\kevin\\inventario\\storage\\app\\backups\\Sittsa_20261113_122431_e11adab9.dump';
        $log = Log::create([
            'accion' => 'revertir_lote_importacion',
            'entidad' => 'import_batch',
            'entidad_id' => 0,
            'descripcion' => 'Lote: 01a1d260-90d5-71a8-b201-9ceaf0dae220; Registros revertidos: 1345; Respaldo: '.$backupPath,
            'user_id' => $administrator->id,
        ]);

        $component = Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('Se revirtieron 1,345 registros de inventario. Se generó un respaldo de seguridad antes de revertir el lote.')
            ->assertSee('01a1d260')
            ->assertSee('Sittsa_20261113_122431_e11adab9.dump')
            ->assertDontSee($backupPath);

        $component->call('showDetails', $log->id)
            ->assertSee('Se revirtieron 1,345 registros de inventario. Se generó un respaldo de seguridad antes de revertir el lote.')
            ->assertSee('Sittsa_20261113_122431_e11adab9.dump')
            ->assertDontSee($backupPath);

        $this->assertStringNotContainsString($backupPath, $log->displayDescription());
    }

    public function test_history_humanizes_legacy_import_application_logs_without_showing_server_path(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $backupPath = 'C:\\Users\\kevin\\inventario\\storage\\app\\backups\\Sittsa_20261112_205129_1703a1f1.dump';
        $log = Log::create([
            'accion' => 'aplicar_lote_importacion',
            'entidad' => 'import_batch',
            'entidad_id' => 0,
            'descripcion' => 'Lote: 01a1d260-90d5-71a8-b201-9ceaf0dae220; Registros importados: 1345; Respaldo: '.$backupPath,
            'user_id' => $administrator->id,
        ]);

        $component = Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('Aplicar lote de importación')
            ->assertSee('Se importaron 1,345 repuestos nuevos al inventario. Se generó un respaldo de seguridad antes de la importación.')
            ->assertSee('01a1d260')
            ->assertSee('Sittsa_20261112_205129_1703a1f1.dump')
            ->assertDontSee($backupPath);

        $component->call('showDetails', $log->id)
            ->assertSee('Se importaron 1,345 repuestos nuevos al inventario.')
            ->assertSee('Sittsa_20261112_205129_1703a1f1.dump')
            ->assertDontSee($backupPath);

        $this->assertStringNotContainsString($backupPath, $log->displayDescription());
    }

    public function test_history_can_export_filtered_records_to_excel(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        $log = $this->createLog($administrator, 'inventory_item', 'Exportación de prueba', now());

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->set('search', 'Exportación de prueba')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_history_print_page_paginates_matching_records_and_preserves_active_filters(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);

        for ($index = 1; $index <= 116; $index++) {
            $this->createLog(
                $administrator,
                'inventory_item',
                'Histórico para impresión '.$index,
                now()->subMinutes($index)
            );
        }
        $this->createLog($administrator, 'purchase_order', 'Otro registro', now());

        $this->actingAs($administrator)
            ->get(route('admin.history.print', [
                'entityFilter' => 'inventory_item',
                'search' => 'Histórico para impresión',
            ]))
            ->assertOk()
            ->assertViewIs('admin.history-print')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 116 && $logs->count() === 100)
            ->assertSee('Histórico para impresión 100')
            ->assertSee('116 registros')
            ->assertSee('Entidad: Repuesto')
            ->assertSee('Búsqueda: Histórico para impresión')
            ->assertDontSee('Otro registro');

        $this->actingAs($administrator)
            ->get(route('admin.history.print', [
                'entityFilter' => 'inventory_item',
                'search' => 'Histórico para impresión',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 116 && $logs->count() === 16)
            ->assertSee('Histórico para impresión 116')
            ->assertSee('Página 2 de 2')
            ->assertDontSee('Histórico para impresión 100');
    }

    public function test_history_print_page_is_restricted_to_administrators(): void
    {
        $warehouseWorker = User::factory()->create(['role' => 'almacenista']);

        $this->actingAs($warehouseWorker)
            ->get(route('admin.history.print'))
            ->assertForbidden();
    }

    public function test_user_edits_are_saved_as_structured_changes_and_shown_as_differences(): void
    {
        Cache::forget('admin.history.entities');
        Cache::forget('admin.history.users');

        $administrator = User::factory()->create(['role' => 'administrador']);
        $target = User::factory()->create([
            'name' => 'Nombre anterior',
            'role' => 'almacenista',
        ]);

        Livewire::actingAs($administrator)
            ->test(UserList::class)
            ->call('editUser', $target->id)
            ->set('editName', 'Nombre nuevo')
            ->call('updateUser');

        $log = Log::query()->where('accion', 'editar')->firstOrFail();
        $this->assertSame([
            ['field' => 'name', 'old' => 'Nombre anterior', 'new' => 'Nombre nuevo'],
        ], $log->cambios);

        Livewire::actingAs($administrator)
            ->test(History::class)
            ->assertSee('Nombre anterior')
            ->assertSee('Nombre nuevo');
    }

    public function test_user_list_uses_the_inventory_pagination_design(): void
    {
        $administrator = User::factory()->create(['role' => 'administrador']);
        User::factory()->count(8)->create();

        Livewire::actingAs($administrator)
            ->test(UserList::class)
            ->assertSee('Paginación de usuarios')
            ->assertSee('Mostrando')
            ->assertSee('Siguiente')
            ->assertSee('wire:click="gotoPage(2,', false)
            ->assertSee('bg-brand-900', false)
            ->call('nextPage')
            ->assertSee('Anterior');
    }

    private function createLog(
        User $user,
        string $entity,
        string $description,
        mixed $createdAt,
        int $entityId = 1
    ): Log {
        $log = Log::create([
            'accion' => 'editar',
            'entidad' => $entity,
            'entidad_id' => $entityId,
            'descripcion' => $description,
            'user_id' => $user->id,
        ]);

        $log->created_at = Carbon::parse($createdAt);
        $log->save();

        return $log;
    }
}
