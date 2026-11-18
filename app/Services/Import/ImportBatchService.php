<?php

namespace App\Services\Import;

use App\Console\Commands\BackupDatabase;
use App\Models\DatabaseBackup;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\Log as AuditLog;
use App\Models\User;
use App\Services\InventoryTypeSuggester;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportBatchService
{
    public function __construct(
        private readonly LegacyExcelParser $parser,
        private readonly InventoryTypeSuggester $typeSuggester,
    ) {}

    public function analyze(ImportBatch $batch): void
    {
        if ($batch->status !== 'analyzing') {
            return;
        }

        $file = \Illuminate\Support\Facades\Storage::disk(config('inventory_import.disk', 'local'))->path($batch->file_path);
        if (! is_file($file)) {
            throw new RuntimeException('No se encuentra el archivo de origen del lote.');
        }

        $inspection = $this->parser->inspect($file, $batch->requested_sheet);
        $counts = [
            'rows_read' => 0,
            'new_rows' => 0,
            'existing_rows' => 0,
            'duplicate_rows' => 0,
            'invalid_rows' => 0,
        ];
        $buffer = [];
        $chunkSize = max(1, (int) config('inventory_import.chunk_size', 500));

        foreach ($this->parser->rows($file, $inspection['sheet']) as $row) {
            $counts['rows_read']++;
            $buffer[] = $row;
            if (count($buffer) >= $chunkSize) {
                $this->classifyAndStore($batch, $buffer, $counts);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $this->classifyAndStore($batch, $buffer, $counts);
        }

        $batch->update([
            ...$counts,
            'sheet_name' => $inspection['sheet'],
            'records_before' => InventoryItem::count(),
            'status' => 'ready',
            'error' => null,
        ]);
    }

    public function queueApply(ImportBatch $batch, int $actorId): void
    {
        $actor = User::query()->whereKey($actorId)
            ->whereIn('role', ['administrador', 'superadministrador'])
            ->first();
        if ($actor === null) {
            throw new RuntimeException('Solo un administrador puede aplicar una importación.');
        }
        if ($batch->status !== 'ready') {
            throw new RuntimeException('El lote no está listo para aplicar.');
        }
        if ((int) $batch->new_rows < 1) {
            throw new RuntimeException('No hay filas nuevas para aplicar.');
        }

        DB::transaction(function () use ($batch, $actor): void {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status !== 'ready') {
                throw new RuntimeException('El lote ya no está disponible para aplicar.');
            }
            if ((int) $lockedBatch->new_rows < 1) {
                throw new RuntimeException('No hay filas nuevas para aplicar.');
            }
            $lockedBatch->update([
                'status' => 'applying',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'error' => null,
            ]);
            DB::table('import_job_outbox')->insert([
                'batch_id' => $lockedBatch->id,
                'job_type' => 'apply',
                'actor_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function queueRevert(ImportBatch $batch, int $actorId): void
    {
        $actor = User::query()->whereKey($actorId)
            ->whereIn('role', ['administrador', 'superadministrador'])
            ->first();
        if ($actor === null || $batch->status !== 'completed') {
            throw new RuntimeException('Solo un administrador puede revertir un lote completado.');
        }

        $itemIds = InventoryItem::withTrashed()
            ->where('import_batch_id', $batch->id)
            ->whereNull('deleted_at')
            ->pluck('id');
        if ($itemIds->isEmpty()) {
            throw new RuntimeException('El lote no tiene artículos activos para revertir.');
        }
        $this->assertNoDependentRecords($itemIds->all());

        DB::transaction(function () use ($batch, $actor): void {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status !== 'completed') {
                throw new RuntimeException('El lote ya no está disponible para revertir.');
            }
            $lockedBatch->update([
                'status' => 'reverting',
                'error' => null,
            ]);
            DB::table('import_job_outbox')->insert([
                'batch_id' => $lockedBatch->id,
                'job_type' => 'revert',
                'actor_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function apply(ImportBatch $batch): void
    {
        if ($batch->status !== 'applying') {
            return;
        }

        $backupPath = $this->createRequiredBackup((int) $batch->approved_by);
        DB::transaction(function () use ($batch, $backupPath): void {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status !== 'applying') {
                throw new RuntimeException('El lote ya no está en estado de aplicación.');
            }

            $existingCount = 0;
            $importedCount = 0;
            $lockedBatch->update(['records_before' => InventoryItem::count()]);

            ImportRow::query()
                ->where('batch_id', $lockedBatch->id)
                ->where('status', 'new')
                ->orderBy('id')
                ->chunkById((int) config('inventory_import.chunk_size', 500), function ($rows) use ($lockedBatch, &$existingCount, &$importedCount): void {
                    $keys = $rows->map(fn (ImportRow $row) => InventoryItem::normalizeSigicovKey($row->sigicov))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $rawSigicovs = $rows->pluck('sigicov')->filter()->unique()->values()->all();
                    $existingKeys = $this->existingKeys($keys, $rawSigicovs);
                    $selectedTypeIds = $rows->pluck('inventory_type_id')->filter()->unique()->values();
                    $activeTypeIds = InventoryType::query()
                        ->where('is_active', true)
                        ->whereIn('id', $selectedTypeIds)
                        ->pluck('id')
                        ->all();

                    foreach ($rows as $row) {
                        $payload = $row->payload ?? [];
                        $key = InventoryItem::normalizeSigicovKey($row->sigicov);
                        if ($key === null || in_array($key, $existingKeys, true)) {
                            $row->update([
                                'status' => 'existente',
                                'reason' => 'El SIGICOV ya existe en inventario; no se sobrescribió.',
                            ]);
                            $existingCount++;

                            continue;
                        }
                        if ($row->inventory_type_id !== null && ! in_array((int) $row->inventory_type_id, $activeTypeIds, true)) {
                            throw new RuntimeException('Uno de los tipos seleccionados ya no está activo. Revisa el catálogo y vuelve a confirmar el lote.');
                        }

                        $now = now()->toDateTimeString();
                        $record = [
                            'item_number' => $payload['item_number'] ?? null,
                            'sigicov' => $row->sigicov,
                            'sigicov_active_key' => $key,
                            'descripcion' => $payload['descripcion'] ?? 'NO DISPONIBLE',
                            'inventory_type_id' => $row->inventory_type_id,
                            'unidad' => $payload['unidad'] ?? 'NO DISPONIBLE',
                            'pasillo' => $payload['pasillo'] ?? 'NO DISPONIBLE',
                            'estante' => $payload['estante'] ?? 'NO DISPONIBLE',
                            'peldaño' => $payload['peldaño'] ?? 'NO DISPONIBLE',
                            'fecha' => $payload['fecha'] ?? null,
                            'realizado_por' => $payload['realizado_por'] ?? 'NO DISPONIBLE',
                            'inventario' => '1',
                            'resp_accesorios' => $payload['resp_accesorios'] ?? 'NO DISPONIBLE',
                            'origen' => 'inventario_inicial',
                            'lote_importacion' => $lockedBatch->id,
                            'import_batch_id' => $lockedBatch->id,
                            'data' => json_encode($payload['data'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ];

                        if (DB::table('inventory_items')->insertOrIgnore($record) === 1) {
                            $row->update(['status' => 'importado', 'reason' => null]);
                            $importedCount++;
                        } else {
                            $row->update([
                                'status' => 'existente',
                                'reason' => 'El SIGICOV entró en conflicto con otro registro; no se sobrescribió.',
                            ]);
                            $existingCount++;
                        }
                    }
                });

            $lockedBatch->update([
                'status' => 'completed',
                'imported_rows' => $importedCount,
                'existing_rows' => $lockedBatch->existing_rows + $existingCount,
                'records_after' => InventoryItem::count(),
                'applied_at' => now(),
                'error' => null,
            ]);

            $itemLabel = $importedCount === 1 ? 'repuesto nuevo' : 'repuestos nuevos';
            $backupName = basename(str_replace('\\', '/', $backupPath));

            AuditLog::create([
                'accion' => 'aplicar_lote_importacion',
                'entidad' => 'import_batch',
                'entidad_id' => 0,
                'descripcion' => "Se importaron {$importedCount} {$itemLabel} al inventario. Se generó un respaldo de seguridad antes de la importación. Lote: {$lockedBatch->id}; Respaldo: {$backupName}",
                'user_id' => $lockedBatch->approved_by,
            ]);

            Log::info('Lote de inventario aplicado', [
                'batch_id' => $lockedBatch->id,
                'backup' => $backupPath,
                'imported_rows' => $importedCount,
                'conflicts' => $existingCount,
            ]);
        });

        if (config('inventory_movements.enabled')) {
            try {
                $exitCode = Artisan::call('inventory:backfill-movements');
                if ($exitCode !== 0) {
                    Log::warning('No se pudieron registrar los movimientos del lote '.$batch->id, [
                        'output' => Artisan::output(),
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('No se pudieron registrar los movimientos del lote '.$batch->id, [
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function retryApply(ImportBatch $batch, int $actorId): void
    {
        $actor = User::query()->whereKey($actorId)
            ->whereIn('role', ['administrador', 'superadministrador'])
            ->first();
        if ($actor === null) {
            throw new RuntimeException('Solo un administrador puede reintentar una importación.');
        }

        DB::transaction(function () use ($batch): void {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status !== 'failed' || $lockedBatch->approved_at === null) {
                throw new RuntimeException('Solo se pueden reintentar lotes fallidos durante la aplicación.');
            }

            $lockedBatch->update([
                'status' => 'ready',
                'error' => null,
            ]);
        });
    }

    public function revert(ImportBatch $batch, int $actorId): ?string
    {
        $actor = User::query()->whereKey($actorId)
            ->whereIn('role', ['administrador', 'superadministrador'])
            ->first();
        if ($actor === null || ! in_array($batch->status, ['completed', 'reverting'], true)) {
            throw new RuntimeException('Solo un administrador puede revertir un lote completado.');
        }

        $itemIds = InventoryItem::withTrashed()
            ->where('import_batch_id', $batch->id)
            ->whereNull('deleted_at')
            ->pluck('id');
        if ($itemIds->isEmpty()) {
            throw new RuntimeException('El lote no tiene artículos activos para revertir.');
        }
        $this->assertNoDependentRecords($itemIds->all());
        $backupPath = $this->createRequiredBackup($actorId);

        DB::transaction(function () use ($batch, $actorId, $itemIds, $backupPath): void {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($lockedBatch->status, ['completed', 'reverting'], true)) {
                throw new RuntimeException('El lote ya no está disponible para revertir.');
            }

            foreach ($itemIds->chunk(500) as $ids) {
                DB::table('inventory_items')
                    ->whereIn('id', $ids)
                    ->where('import_batch_id', $lockedBatch->id)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'sigicov_active_key' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lockedBatch->update([
                'status' => 'reverted',
                'reverted_at' => now(),
            ]);

            $revertedCount = $itemIds->count();
            $recordLabel = $revertedCount === 1 ? 'registro' : 'registros';
            $backupName = basename(str_replace('\\', '/', $backupPath));

            AuditLog::create([
                'accion' => 'revertir_lote_importacion',
                'entidad' => 'import_batch',
                'entidad_id' => 0,
                'descripcion' => "Se revirtieron {$revertedCount} {$recordLabel} de inventario. Se generó un respaldo de seguridad antes de revertir el lote. Lote: {$lockedBatch->id}; Respaldo: {$backupName}",
                'user_id' => $actorId,
            ]);
        });

        return $backupPath;
    }

    private function classifyAndStore(ImportBatch $batch, array $rows, array &$counts): void
    {
        $keys = [];
        $sigicovs = [];
        $keyHashes = [];
        foreach ($rows as $row) {
            if ($row['invalid_reason'] !== null) {
                continue;
            }

            $sigicov = $row['payload']['sigicov'] ?? null;
            $key = InventoryItem::normalizeSigicovKey($sigicov);
            if ($key !== null) {
                $keys[] = $key;
                $sigicovs[] = $sigicov;
                $keyHashes[] = hash('sha256', $key);
            }
        }

        $keyHashes = array_values(array_unique($keyHashes));
        $seenHashes = $keyHashes === []
            ? []
            : DB::table('import_batch_seen_sigicovs')
                ->where('batch_id', $batch->id)
                ->whereIn('sigicov_key_hash', $keyHashes)
                ->pluck('sigicov_key_hash')
                ->all();
        $seen = array_fill_keys($seenHashes, true);
        $existingKeys = $this->existingKeys(array_unique($keys), array_unique($sigicovs));
        $existing = array_fill_keys($existingKeys, true);
        $seenRows = [];
        $stagedRows = [];
        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $sigicov = $row['payload']['sigicov'] ?? null;
            $key = InventoryItem::normalizeSigicovKey($sigicov);
            $keyHash = $key === null ? null : hash('sha256', $key);

            if ($row['invalid_reason'] !== null) {
                $status = 'invalida';
                $reason = $row['invalid_reason'];
                $counts['invalid_rows']++;
            } elseif ($key === null) {
                $status = 'sin_sigicov';
                $reason = 'SIGICOV vacío o no numérico.';
                $counts['invalid_rows']++;
            } elseif (isset($seen[$keyHash])) {
                $status = 'repetido';
                $reason = 'SIGICOV repetido en el archivo; se conserva la primera fila.';
                $counts['duplicate_rows']++;
            } else {
                $seen[$keyHash] = true;
                $seenRows[] = [
                    'batch_id' => $batch->id,
                    'sigicov_key_hash' => $keyHash,
                ];
                $status = isset($existing[$key]) ? 'existente' : 'new';
                $reason = $status === 'existente'
                    ? 'El SIGICOV ya existe en inventario, incluso si está eliminado.'
                    : null;
                $counts[$status === 'new' ? 'new_rows' : 'existing_rows']++;
            }

            $suggestion = $this->typeSuggester->suggest($row['payload']['descripcion'] ?? null);
            $stagedRows[] = [
                'batch_id' => $batch->id,
                'excel_row' => $row['excel_row'],
                'status' => $status,
                'sigicov' => $sigicov,
                'suggested_inventory_type_id' => $suggestion?->id,
                'reason' => $reason,
                'original_values' => json_encode($row['original'], JSON_THROW_ON_ERROR),
                'payload' => json_encode($row['payload'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($batch, $counts, $seenRows, $stagedRows): void {
            foreach (array_chunk($seenRows, 500) as $chunk) {
                DB::table('import_batch_seen_sigicovs')->insert($chunk);
            }
            foreach (array_chunk($stagedRows, 200) as $chunk) {
                DB::table('import_rows')->insert($chunk);
            }

            $batch->newQuery()->whereKey($batch->id)->update([
                'rows_read' => $counts['rows_read'],
                'new_rows' => $counts['new_rows'],
                'existing_rows' => $counts['existing_rows'],
                'duplicate_rows' => $counts['duplicate_rows'],
                'invalid_rows' => $counts['invalid_rows'],
            ]);
        });
    }

    private function existingKeys(array $keys, array $rawSigicovs): array
    {
        if ($keys === []) {
            return [];
        }

        $active = InventoryItem::query()
            ->whereIn('sigicov_active_key', $keys)
            ->pluck('sigicov_active_key')
            ->all();
        $deleted = InventoryItem::withTrashed()
            ->whereNotNull('deleted_at')
            ->whereIn('sigicov', $rawSigicovs)
            ->where(function ($query): void {
                $query->whereNull('import_batch_id')
                    ->orWhereDoesntHave('importBatch', fn ($batchQuery) => $batchQuery->where('status', 'reverted'));
            })
            ->pluck('sigicov')
            ->map(fn ($sigicov) => InventoryItem::normalizeSigicovKey($sigicov))
            ->all();

        return array_values(array_unique(array_filter([...$active, ...$deleted])));
    }

    private function createRequiredBackup(int $actorId): ?string
    {
        $driver = DB::connection()->getDriverName();
        $maySkip = app()->environment(['local', 'testing'])
            && ! (bool) config('inventory_import.require_backup', true);
        if ($driver === 'pgsql') {
            if (! app()->runningInConsole()) {
                throw new RuntimeException('El respaldo previo debe ejecutarse desde un worker de consola.');
            }

            $arguments = ['--trigger' => 'pre_import'];
            if ($actorId > 0) {
                $arguments['--user'] = $actorId;
            }

            $exitCode = Artisan::call('db:backup', $arguments);
            $output = trim(Artisan::output());
            if ($exitCode !== BackupDatabase::SUCCESS) {
                $detail = $output !== '' ? ' Detalle: '.mb_substr($output, 0, 1000) : '';
                throw new RuntimeException('No se pudo crear el respaldo PostgreSQL verificado; no se aplicó ningún registro.'.$detail);
            }

            $backup = DatabaseBackup::latestVerified(60);
            if ($backup === null) {
                throw new RuntimeException('No hay un respaldo PostgreSQL verificado reciente; no se aplicó ningún registro.');
            }

            return $backup->path;
        }

        if ($maySkip) {
            return null;
        }
        if ($driver !== 'sqlite' || DB::connection()->getDatabaseName() === ':memory:') {
            throw new RuntimeException('La importación requiere PostgreSQL con respaldo verificado o una base SQLite persistente con respaldo.');
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/inventory_pre_import_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.sqlite';
        DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($path));
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('No se pudo verificar el respaldo SQLite; no se aplicó ningún registro.');
        }

        return $path;
    }

    private function assertNoDependentRecords(array $itemIds): void
    {
        foreach (config('inventory_import.dependent_tables', []) as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (array_chunk($itemIds, 500) as $ids) {
                if (DB::table($table)->whereIn($column, $ids)->exists()) {
                    throw new RuntimeException("No se puede revertir: {$table}.{$column} ya referencia artículos del lote.");
                }
            }
        }
    }
}
