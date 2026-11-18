<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportInventoryExcel extends Command
{
    protected $signature = 'inventory:import-excel
                            {archivo : Ruta del archivo XLSX, CSV u ODS}
                            {--hoja= : Nombre de la hoja}
                            {--corte= : Fecha de corte en formato YYYY-MM-DD}
                            {--user= : ID de un administrador}
                            {--apply : Aplicar después de analizar y mostrar los conteos}';

    protected $description = 'Analiza un archivo de inventario y opcionalmente aplica las filas nuevas con respaldo verificado';

    public function handle(ImportBatchService $imports): int
    {
        $source = realpath($this->argument('archivo'));
        if ($source === false || ! is_file($source) || ! is_readable($source)) {
            $this->error('El archivo indicado no existe o no se puede leer.');

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'csv', 'ods'], true)) {
            $this->error('Formato no compatible. Usa XLSX, CSV u ODS.');

            return self::FAILURE;
        }

        $userId = filter_var($this->option('user'), FILTER_VALIDATE_INT);
        $user = $userId === false ? null : User::query()
            ->whereKey($userId)
            ->whereIn('role', ['administrador', 'superadministrador'])
            ->first();
        if ($user === null) {
            $this->error('Debes indicar --user con el ID de un administrador existente.');

            return self::FAILURE;
        }

        $cutoff = $this->option('corte');
        if (Validator::make(['corte' => $cutoff], ['corte' => ['nullable', 'date_format:Y-m-d']])->fails()) {
            $this->error('La fecha --corte debe usar el formato YYYY-MM-DD y ser válida.');

            return self::FAILURE;
        }

        $maxBytes = (int) config('inventory_import.max_file_kilobytes', 102400) * 1024;
        $size = filesize($source);
        if ($size === false || $size > $maxBytes) {
            $this->error('El archivo supera el tamaño máximo configurado o no se pudo determinar su tamaño.');

            return self::FAILURE;
        }

        $hash = hash_file('sha256', $source);
        if ($hash === false) {
            $this->error('No se pudo calcular la huella del archivo.');

            return self::FAILURE;
        }
        if (ImportBatch::query()->where('file_hash', $hash)->blocksDuplicateUpload()->exists()) {
            $this->error('Este archivo ya tiene un lote activo o completado.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('inventory_import.disk', 'local'));
        $relativePath = trim(config('inventory_import.directory', 'imports'), '/').'/'.Str::uuid().'.'.$extension;
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            $this->error('No se pudo abrir el archivo de origen.');

            return self::FAILURE;
        }

        try {
            $stored = $disk->put($relativePath, $stream);
        } catch (Throwable $exception) {
            $disk->delete($relativePath);
            $this->error('No se pudo guardar el archivo: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            $this->error('No se pudo guardar el archivo en el almacenamiento privado de importaciones.');

            return self::FAILURE;
        }

        $batch = null;
        try {
            $batch = ImportBatch::create([
                'user_id' => $user->id,
                'file_name' => basename($source),
                'file_path' => $relativePath,
                'file_hash' => $hash,
                'file_size' => $size,
                'status' => 'analyzing',
                'requested_sheet' => $this->option('hoja') ?: null,
                'cutoff_date' => $cutoff,
            ]);

            $imports->analyze($batch);
            $batch->refresh();
            $this->line("Lote: {$batch->id}");
            $this->line("Estado: {$batch->status}");
            $this->line("Filas leídas: {$batch->rows_read}");
            $this->line("Nuevas: {$batch->new_rows}");
            $this->line("Existentes: {$batch->existing_rows}");
            $this->line("Repetidas: {$batch->duplicate_rows}");
            $this->line("Inválidas: {$batch->invalid_rows}");

            if (! $this->option('apply')) {
                $this->info('Solo se realizó el análisis; inventory_items no fue modificado.');

                return self::SUCCESS;
            }

            if ($batch->status !== 'ready') {
                throw new RuntimeException('El lote no quedó listo para aplicar.');
            }

            $batch->update([
                'status' => 'applying',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            $imports->apply($batch->fresh());
            $batch->refresh();
            $this->info("Aplicación completada: {$batch->imported_rows} filas insertadas.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($batch !== null) {
                $batch->update([
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
            } else {
                $disk->delete($relativePath);
            }

            Log::error('Falló la importación Artisan de inventario', [
                'batch_id' => $batch?->id,
                'error' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
