<?php

namespace Tests\Feature;

use App\Jobs\ApplyImportBatch;
use App\Jobs\RevertImportBatch;
use App\Livewire\Admin\Imports\Index as ImportBatchIndex;
use App\Livewire\Admin\Imports\Show as ImportBatchShow;
use App\Models\ImportBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryType;
use App\Models\Log;
use App\Models\SpareRequest;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class ImportBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $workbookPath;
    private string $storageRoot;
    private string $diskName;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inventory_import.require_backup' => false]);
        $this->workbookPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'import-test-'.bin2hex(random_bytes(5)).'.xlsx';
        $this->storageRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'import-storage-'.bin2hex(random_bytes(5));
        $this->diskName = 'import-test';
        config([
            "filesystems.disks.{$this->diskName}" => [
                'driver' => 'local',
                'root' => $this->storageRoot,
                'throw' => false,
            ],
            'inventory_import.disk' => $this->diskName,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->workbookPath)) {
            unlink($this->workbookPath);
        }
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_analysis_stages_rows_and_does_not_change_inventory(): void
    {
        config(['inventory_import.chunk_size' => 2]);
        $active = InventoryItem::create(['sigicov' => '111', 'descripcion' => 'Existente']);
        $deleted = InventoryItem::create(['sigicov' => '222', 'descripcion' => 'Eliminado']);
        $deleted->delete();
        $batch = $this->makeBatch([
            ['111', 'Ya existe', '1', 'TOTAL INV. JUNIO'],
            ['222', 'Fue eliminado', '2', 'TOTAL INV. JUNIO'],
            ['333', 'Nuevo', '10', 'TOTAL INV. JUNIO'],
            ['333', 'Duplicado en archivo', '8', 'TOTAL INV. JUNIO'],
            ['ABC', 'No numérico', '7', 'TOTAL INV. JUNIO'],
            ['111', 'Duplicado en otro bloque', '1', 'TOTAL INV. JUNIO'],
        ]);
        $before = InventoryItem::count();

        app(ImportBatchService::class)->analyze($batch);
        $batch->refresh();

        $this->assertSame('ready', $batch->status);
        $this->assertSame(6, $batch->rows_read);
        $this->assertSame(1, $batch->new_rows);
        $this->assertSame(2, $batch->existing_rows);
        $this->assertSame(2, $batch->duplicate_rows);
        $this->assertSame(1, $batch->invalid_rows);
        $this->assertSame($before, InventoryItem::count());
        $this->assertSame('existente', $batch->rows()->where('sigicov', '111')->value('status'));
        $this->assertSame('existente', $batch->rows()->where('sigicov', '222')->value('status'));
        $this->assertSame('repetido', $batch->rows()->where('excel_row', 11)->value('status'));
        $this->assertSame('repetido', $batch->rows()->where('excel_row', 13)->value('status'));
        $this->assertSame($active->id, InventoryItem::find($active->id)->id);
    }

    public function test_inventory_changes_only_after_apply_is_queued_and_processed(): void
    {
        $batch = $this->makeBatch([
            ['9001', 'Repuesto nuevo', '12', 'TOTAL INV. JUNIO'],
            ['9002', 'Respaldo subtotal', '4', 'TOTAL'],
            ['9003', 'Stock cero', '0', 'TOTAL INV. JUNIO', 'A1', '5'],
        ]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $batch->refresh();
        $this->assertSame(0, InventoryItem::count());

        Queue::fake();
        $admin = User::factory()->create(['role' => 'administrador']);
        $service->queueApply($batch, $admin->id);

        $this->assertSame(0, InventoryItem::count());
        $this->assertSame('applying', $batch->fresh()->status);
        $this->assertDatabaseHas('import_job_outbox', [
            'batch_id' => $batch->id,
            'job_type' => 'apply',
            'dispatched_at' => null,
        ]);
        DB::table('import_job_outbox')->where('batch_id', $batch->id)->update([
            'claimed_at' => now()->subMinutes(6),
            'attempts' => 1,
        ]);
        Artisan::call('imports:dispatch-outbox');
        Queue::assertPushed(ApplyImportBatch::class);
        $outboxMessage = DB::table('import_job_outbox')->where('batch_id', $batch->id)->first();
        $this->assertNotNull($outboxMessage->dispatched_at);
        $this->assertSame(2, (int) $outboxMessage->attempts);

        app(ImportBatchService::class)->apply($batch->fresh());

        $batch->refresh();
        $audit = Log::query()->where('accion', 'aplicar_lote_importacion')->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(3, InventoryItem::count());
        $this->assertStringStartsWith(
            "Se importaron 3 repuestos nuevos al inventario. Se generó un respaldo de seguridad antes de la importación. Lote: {$batch->id}; Respaldo: ",
            $audit->descripcion
        );
        $this->assertStringNotContainsString('\\', $audit->descripcion);
        $this->assertStringNotContainsString('/', $audit->descripcion);
        $this->assertSame('inventario_inicial', InventoryItem::where('sigicov', '9001')->value('origen'));
        $this->assertSame($batch->id, InventoryItem::where('sigicov', '9001')->value('import_batch_id'));
        $this->assertSame('1', InventoryItem::where('sigicov', '9001')->value('inventario'));
        $this->assertSame('1', InventoryItem::where('sigicov', '9002')->value('inventario'));
        $this->assertSame('1', InventoryItem::where('sigicov', '9003')->value('inventario'));
        $this->assertSame([], InventoryItem::where('sigicov', '9001')->firstOrFail()->data);
        $this->assertSame(3, InventoryMovement::where('tipo', 'inventario_inicial')->count());
        $this->assertSame(3, (int) InventoryMovement::sum('delta'));
    }

    public function test_import_type_suggestion_requires_selection_and_confirmed_type_is_imported(): void
    {
        $type = InventoryType::create([
            'name' => 'Filtros especiales de prueba',
            'keywords' => ['elemento filtrante especializado'],
        ]);
        $batch = $this->makeBatch([
            ['9101', 'Elemento filtrante especializado', '3', 'INVENTARIO'],
        ]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $row = $batch->rows()->where('status', 'new')->firstOrFail();

        $this->assertSame($type->id, $row->suggested_inventory_type_id);
        $this->assertNull($row->inventory_type_id);

        $admin = User::factory()->create(['role' => 'administrador']);
        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->call('setRowType', $row->id, (string) $type->id)
            ->assertHasNoErrors();
        $this->assertSame($type->id, $row->fresh()->inventory_type_id);

        Queue::fake();
        $service->queueApply($batch->fresh(), $admin->id);
        $service->apply($batch->fresh());

        $this->assertSame($type->id, InventoryItem::where('sigicov', '9101')->value('inventory_type_id'));
    }

    public function test_revert_rejects_lots_with_dependent_requests(): void
    {
        $batch = $this->makeBatch([['7001', 'Relacionado', '1', 'INVENTARIO']]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $batch->update(['status' => 'applying', 'approved_by' => User::factory()->create(['role' => 'administrador'])->id]);
        $service->apply($batch);
        $item = InventoryItem::where('import_batch_id', $batch->id)->firstOrFail();
        $user = User::factory()->create(['role' => 'almacenista']);
        SpareRequest::create([
            'almacenista_id' => $user->id,
            'cantidad' => 1,
            'descripcion' => 'Solicitud relacionada',
            'tipo' => 'eliminacion',
            'inventory_item_id' => $item->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('spare_requests.inventory_item_id');
        $service->revert($batch->fresh(), $batch->approved_by);
    }

    public function test_reverted_lot_soft_deletes_only_its_items_and_can_be_reversed_once(): void
    {
        $batch = $this->makeBatch([['8001', 'Lote reversible', '3', 'INVENTARIO']]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);
        $batch->update(['status' => 'applying', 'approved_by' => $admin->id]);
        $service->apply($batch);

        Queue::fake();
        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->assertSee('Respaldar y revertir lote')
            ->call('revert')
            ->assertHasNoErrors()
            ->assertSee('La solicitud de reversión fue recibida.')
            ->assertDontSee('queue:listen')
            ->assertDontSee('queue:work');

        $this->assertSame('reverting', $batch->fresh()->status);
        Artisan::call('imports:dispatch-outbox');
        Queue::assertPushed(RevertImportBatch::class);
        (new RevertImportBatch($batch->id, $admin->id))->handle($service);

        $this->assertSame('reverted', $batch->fresh()->status);
        $this->assertSoftDeleted('inventory_items', ['import_batch_id' => $batch->id]);
        $this->assertSame(0, InventoryItem::count());

        $audit = Log::query()->where('accion', 'revertir_lote_importacion')->firstOrFail();
        $this->assertStringStartsWith(
            "Se revirtieron 1 registro de inventario. Se generó un respaldo de seguridad antes de revertir el lote. Lote: {$batch->id}; Respaldo: ",
            $audit->descripcion
        );
        $this->assertStringNotContainsString('\\', $audit->descripcion);
        $this->assertStringNotContainsString('/', $audit->descripcion);

        $reimport = $this->makeBatch([['8001', 'Lote reversible', '3', 'INVENTARIO']]);
        $service->analyze($reimport);
        $this->assertSame(1, $reimport->fresh()->new_rows);
        $reimport->update(['status' => 'applying', 'approved_by' => $admin->id]);
        $service->apply($reimport->fresh());
        $this->assertSame('completed', $reimport->fresh()->status);
        $this->assertSame($reimport->id, InventoryItem::where('sigicov', '8001')->value('import_batch_id'));
    }

    public function test_legacy_sigicov_unique_index_allows_repeated_import_and_revert_cycles_after_migration(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->unique('sigicov', 'inventory_items_sigicov_unique');
        });

        $migration = require database_path('migrations/2026_11_15_000006_drop_legacy_sigicov_unique_index.php');
        $migration->up();

        $indexes = Schema::getIndexes('inventory_items');
        $this->assertFalse(collect($indexes)->contains(
            fn (array $index): bool => $index['name'] === 'inventory_items_sigicov_unique'
        ));
        $this->assertTrue(collect($indexes)->contains(
            fn (array $index): bool => $index['name'] === 'inventory_items_sigicov_active_key_unique'
        ));

        $service = app(ImportBatchService::class);
        $admin = User::factory()->create(['role' => 'administrador']);

        foreach (range(1, 3) as $cycle) {
            $batch = $this->makeBatch([['8001', 'Lote reversible', '3', 'INVENTARIO']]);
            $service->analyze($batch);
            $this->assertSame(1, $batch->fresh()->new_rows);

            $batch->update(['status' => 'applying', 'approved_by' => $admin->id]);
            $service->apply($batch->fresh());
            $this->assertSame('completed', $batch->fresh()->status);
            $this->assertSame(1, $batch->fresh()->imported_rows);
            $this->assertSame($batch->id, InventoryItem::where('sigicov', '8001')->value('import_batch_id'));

            $service->revert($batch->fresh(), $admin->id);
            $this->assertSame('reverted', $batch->fresh()->status);
            $this->assertSame(0, InventoryItem::query()->count());
            $this->assertSame($cycle, InventoryItem::withTrashed()->count());
        }
    }

    public function test_failed_revert_job_restores_the_completed_state_without_changing_inventory(): void
    {
        $batch = $this->makeBatch([['8002', 'Lote con respaldo fallido', '1', 'INVENTARIO']]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);
        $batch->update(['status' => 'applying', 'approved_by' => $admin->id]);
        $service->apply($batch);
        $batch->update(['status' => 'reverting']);

        (new RevertImportBatch($batch->id, $admin->id))->failed(new RuntimeException('No se pudo verificar el respaldo.'));

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('No se pudo verificar el respaldo.', $batch->fresh()->error);
        $this->assertSame(1, InventoryItem::count());
    }

    public function test_import_pages_and_upload_are_restricted_to_administrators(): void
    {
        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $this->actingAs($warehouse)
            ->get(route('imports.index'))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin)
            ->get(route('imports.index'))
            ->assertOk();

        $this->writeWorkbook([['6001', 'Carga segura', '5', 'INVENTARIO']]);
        $upload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        Queue::fake();
        $response = $this->post(route('imports.store'), [
            'file' => $upload,
            'cutoff_date' => '2025-06-30',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, InventoryItem::count());
        $batch = ImportBatch::query()->latest()->firstOrFail();
        $this->assertSame('analyzing', $batch->status);
        $this->assertDatabaseHas('import_job_outbox', [
            'batch_id' => $batch->id,
            'job_type' => 'analyze',
            'dispatched_at' => null,
        ]);
        Artisan::call('imports:dispatch-outbox');
        Queue::assertPushed(\App\Jobs\AnalyzeImportBatch::class);

        $duplicateUpload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        $this->post(route('imports.store'), [
            'file' => $duplicateUpload,
            'cutoff_date' => '2025-06-30',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
        $this->assertSame(1, ImportBatch::count());

        $batch->update(['status' => 'completed', 'imported_rows' => 1]);
        $completedDuplicateUpload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        $this->post(route('imports.store'), [
            'file' => $completedDuplicateUpload,
            'cutoff_date' => '2025-06-30',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
        $this->assertSame(1, ImportBatch::count());

        $batch->update(['imported_rows' => 0]);
        $reimportUpload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        $this->post(route('imports.store'), [
            'file' => $reimportUpload,
            'cutoff_date' => '2025-06-30',
        ])->assertRedirect();
        $this->assertSame(2, ImportBatch::count());

        $batch->update(['status' => 'reverted']);
        ImportBatch::query()->where('id', '<>', $batch->id)->update(['status' => 'reverted']);
        $revertedUpload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        $this->post(route('imports.store'), [
            'file' => $revertedUpload,
            'cutoff_date' => '2025-06-30',
        ])->assertRedirect();
        $this->assertSame(3, ImportBatch::count());
    }

    public function test_cutoff_date_is_required_on_upload_and_defaults_to_june_30_2025(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);
        $this->actingAs($admin)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertSee('value="2025-06-30"', false)
            ->assertSee('required', false);

        $this->writeWorkbook([['6050', 'Fecha obligatoria', '5', 'INVENTARIO']]);
        $upload = UploadedFile::fake()->createWithContent('inventario.xlsx', file_get_contents($this->workbookPath));
        Queue::fake();
        $this->post(route('imports.store'), ['file' => $upload])
            ->assertRedirect()
            ->assertSessionHasErrors('cutoff_date');

        $this->assertSame(0, ImportBatch::count());
    }

    public function test_preview_can_filter_rows_with_incomplete_locations(): void
    {
        $batch = $this->makeBatch([
            ['5001', 'Ubicación incompleta', '1', 'INVENTARIO', ''],
            ['5002', 'Ubicación completa', '2', 'INVENTARIO', 'A2'],
        ]);
        app(ImportBatchService::class)->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->set('rowStatus', 'incomplete_location')
            ->assertSee('5001')
            ->assertDontSee('5002');
    }

    public function test_non_numeric_inventory_value_does_not_invalidate_sigicov_row(): void
    {
        $batch = $this->makeBatch([['4001', 'Cantidad inválida', 'no-numérica', 'INVENTARIO']]);

        app(ImportBatchService::class)->analyze($batch);

        $this->assertSame('ready', $batch->fresh()->status);
        $this->assertSame(1, $batch->fresh()->new_rows);
        $this->assertSame('new', $batch->rows()->value('status'));
        $this->assertSame('1', $batch->rows()->value('payload')['inventario']);
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_stale_running_batches_show_user_friendly_warning_on_index_and_detail(): void
    {
        $staleBatches = [];
        foreach (['analyzing', 'applying'] as $status) {
            $batch = $this->makeBatch([[(string) random_int(4101, 4199), 'En cola', '1', 'INVENTARIO']]);
            DB::table('import_batches')->where('id', $batch->id)->update([
                'status' => $status,
                'updated_at' => now()->subMinutes(3),
            ]);
            $staleBatches[] = $batch->fresh();
        }
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchIndex::class)
            ->assertSee('El proceso está tardando más de lo esperado.')
            ->assertDontSee('queue:listen')
            ->assertDontSee('queue:work');
        foreach ($staleBatches as $batch) {
            Livewire::actingAs($admin)
                ->test(ImportBatchShow::class, ['batch' => $batch])
                ->assertSee('El proceso está tardando más de lo esperado.')
                ->assertDontSee('queue:listen')
                ->assertDontSee('queue:work');
        }
    }

    public function test_retry_resets_only_failed_apply_batches_and_preserves_staged_rows(): void
    {
        $batch = $this->makeBatch([['4201', 'Reintentar', '1', 'INVENTARIO']]);
        app(ImportBatchService::class)->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);
        $rowCount = $batch->rows()->count();
        $batch->update([
            'status' => 'failed',
            'approved_by' => $admin->id,
            'approved_at' => now()->subMinute(),
            'error' => 'Fallo simulado durante la aplicación',
        ]);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->assertSee('Reintentar')
            ->call('retry')
            ->assertSee('Aplicar');

        $this->assertSame('ready', $batch->fresh()->status);
        $this->assertNull($batch->fresh()->error);
        $this->assertSame($rowCount, $batch->rows()->count());
    }

    public function test_apply_is_unavailable_and_rejected_when_no_new_rows_exist(): void
    {
        InventoryItem::create(['sigicov' => '4250', 'descripcion' => 'Ya existe']);
        $batch = $this->makeBatch([['4250', 'Duplicado', '1', 'INVENTARIO']]);
        $service = app(ImportBatchService::class);
        $service->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->assertSee('disabled', false)
            ->call('apply')
            ->assertSet('actionError', 'No hay filas nuevas para aplicar.')
            ->assertDontSee('wire:confirm="¿Aplicar las filas nuevas?', false);

        try {
            $service->queueApply($batch->fresh(), $admin->id);
            $this->fail('Se permitió aplicar un lote sin filas nuevas.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No hay filas nuevas para aplicar.', $exception->getMessage());
        }

        $this->assertSame('ready', $batch->fresh()->status);
        $this->assertSame(1, InventoryItem::count());
    }

    public function test_failed_analysis_batch_does_not_offer_or_allow_retry(): void
    {
        $batch = $this->makeBatch([['4301', 'Análisis fallido', '1', 'INVENTARIO']]);
        $batch->update(['status' => 'failed', 'error' => 'Fallo durante el análisis']);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->assertDontSee('Reintentar')
            ->call('retry')
            ->assertSet('actionError', 'Solo se pueden reintentar lotes fallidos durante la aplicación.');
        $this->assertSame('failed', $batch->fresh()->status);
    }

    public function test_non_numeric_inventory_totals_are_ignored_in_batch_detail(): void
    {
        $batch = $this->makeBatch([
            ['4401', 'Cantidad inválida', 'no-numérica', 'INVENTARIO'],
            ['4402', 'Cantidad válida', '2', 'INVENTARIO'],
        ]);
        app(ImportBatchService::class)->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->set('rowStatus', 'new')
            ->assertSee('4401')
            ->assertSee('4402')
            ->assertSee('Unidades');
    }

    public function test_batch_detail_translates_statuses_and_supports_counted_filters_and_search(): void
    {
        InventoryItem::create(['sigicov' => '4452', 'descripcion' => 'Ya existente']);
        $batch = $this->makeBatch([
            ['4451', 'Repuesto nuevo', '3', 'TOTAL INV. JUNIO'],
            ['4452', 'Ya existente', '4', 'TOTAL INV. JUNIO'],
            ['4451', 'Repuesto repetido', '5', 'TOTAL INV. JUNIO'],
            ['ABC', 'Sin código', '6', 'TOTAL INV. JUNIO'],
        ]);
        app(ImportBatchService::class)->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);

        Livewire::actingAs($admin)
            ->test(ImportBatchShow::class, ['batch' => $batch])
            ->assertSee('Listo para aplicar')
            ->assertSee('Nuevas')
            ->assertSee('Existentes')
            ->assertSee('Repetidas')
            ->assertSee('Inválidas')
            ->assertSee('(1)')
            ->assertSee('Cada SIGICOV válido se importará como una unidad')
            ->assertDontSee('inventory_items')
            ->set('rowStatus', 'invalid')
            ->assertSee('SIN CÓDIGO')
            ->assertDontSee('REPUESTO NUEVO')
            ->set('rowStatus', 'all')
            ->set('search', 'Repuesto nuevo')
            ->assertSee('REPUESTO NUEVO')
            ->assertDontSee('YA EXISTENTE');
    }

    public function test_artisan_import_command_only_analyzes_without_apply_option(): void
    {
        $this->writeWorkbook([['4501', 'Solo análisis', '7', 'INVENTARIO']]);
        $admin = User::factory()->create(['role' => 'administrador']);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitCode = Artisan::call('inventory:import-excel', [
            'archivo' => $this->workbookPath,
            '--user' => $admin->id,
            '--hoja' => 'JUNIO 2025 (2)',
            '--corte' => '2025-06-30',
        ], $output);

        $commandOutput = $output->fetch();
        $this->assertSame(0, $exitCode, $commandOutput);
        $this->assertStringContainsString('Nuevas: 1', $commandOutput);
        $this->assertStringContainsString('inventory_items no fue modificado', $commandOutput);
        $batch = ImportBatch::query()->sole();
        $this->assertSame('ready', $batch->status);
        $this->assertSame('JUNIO 2025 (2)', $batch->requested_sheet);
        $this->assertSame('2025-06-30', $batch->cutoff_date->format('Y-m-d'));
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_artisan_import_command_requires_admin_and_apply_option_to_write_inventory(): void
    {
        $this->writeWorkbook([['4601', 'Aplicación Artisan', '8', 'INVENTARIO']]);
        $missingUserExitCode = Artisan::call('inventory:import-excel', [
            'archivo' => $this->workbookPath,
        ]);
        $this->assertSame(1, $missingUserExitCode);
        $this->assertSame(0, ImportBatch::count());

        $warehouse = User::factory()->create(['role' => 'almacenista']);
        $exitCode = Artisan::call('inventory:import-excel', [
            'archivo' => $this->workbookPath,
            '--user' => $warehouse->id,
        ]);
        $this->assertSame(1, $exitCode);
        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, InventoryItem::count());

        $admin = User::factory()->create(['role' => 'superadministrador']);
        $exitCode = Artisan::call('inventory:import-excel', [
            'archivo' => $this->workbookPath,
            '--user' => $admin->id,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $batch = ImportBatch::query()->sole();
        $this->assertSame('completed', $batch->status);
        $this->assertSame($admin->id, $batch->approved_by);
        $this->assertSame($batch->id, InventoryItem::where('sigicov', '4601')->value('import_batch_id'));
        $this->assertSame(1, InventoryItem::count());
    }

    public function test_omitted_rows_can_be_downloaded_before_apply_without_formula_values(): void
    {
        $batch = $this->makeBatch([['=HYPERLINK("https://example.test")', '=1+1', '1', 'INVENTARIO']]);
        app(ImportBatchService::class)->analyze($batch);
        $admin = User::factory()->create(['role' => 'administrador']);

        $response = $this->actingAs($admin)->get(route('imports.omitted', $batch));

        $response->assertOk();
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    private function makeBatch(array $inventoryRows): ImportBatch
    {
        $this->writeWorkbook($inventoryRows);
        $relativePath = 'imports/'.basename($this->workbookPath);
        Storage::disk($this->diskName)->put($relativePath, file_get_contents($this->workbookPath));

        return ImportBatch::create([
            'user_id' => User::factory()->create(['role' => 'administrador'])->id,
            'file_name' => basename($this->workbookPath),
            'file_path' => $relativePath,
            'file_hash' => hash_file('sha256', $this->workbookPath),
            'file_size' => filesize($this->workbookPath),
            'status' => 'analyzing',
            'cutoff_date' => '2025-06-30',
        ]);
    }

    private function writeWorkbook(array $inventoryRows): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('JUNIO 2025 (2)');
        $headers = [
            'A6' => 'SIGICOV',
            'B6' => 'DESCRIPCIÓN',
            'C6' => 'UNIDAD',
            'D6' => 'PASILLO',
            'E6' => 'ESTANTE',
            'F6' => 'PELDAÑO',
            'G6' => 'FECHA',
            'H6' => 'REALIZADO POR',
            'I6' => 'RESP. ACCESORIOS',
            'J6' => 'TOTAL INV.',
            'J7' => 'JUNIO',
            'K6' => 'TOTAL',
            'L6' => 'INVENTARIO',
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        foreach ($inventoryRows as $offset => $values) {
            [$sigicov, $description, $quantity, $source, $aisle, $fallbackTotal] = array_pad($values, 6, null);
            $line = 8 + $offset;
            $sheet->setCellValue("A{$line}", $sigicov);
            $sheet->setCellValue("B{$line}", $description);
            $sheet->setCellValue("C{$line}", 'PZA');
            $sheet->setCellValue("D{$line}", $aisle ?? 'A1');
            $sheet->setCellValue("E{$line}", 'E1');
            $sheet->setCellValue("F{$line}", 'P1');
            $sheet->setCellValue("G{$line}", '2025-06-30');
            $sheet->setCellValue("H{$line}", 'Operador');
            $sheet->setCellValue("I{$line}", 'Accesorios');
            $column = match ($source) {
                'TOTAL INV. JUNIO' => 'J',
                'TOTAL' => 'K',
                default => 'L',
            };
            $sheet->setCellValue("{$column}{$line}", $quantity);
            if ($fallbackTotal !== null) {
                $sheet->setCellValue("K{$line}", $fallbackTotal);
            }
        }

        (new Xlsx($spreadsheet))->save($this->workbookPath);
        $spreadsheet->disconnectWorksheets();
    }
}
