<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $batchId) {}

    public function handle(ImportBatchService $service): void
    {
        $service->analyze(ImportBatch::findOrFail($this->batchId));
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => 'failed',
            'error' => mb_substr($exception?->getMessage() ?? 'No se pudo analizar el archivo.', 0, 2000),
            'updated_at' => now(),
        ]);
        Log::error('Falló el análisis de un lote de importación', [
            'batch_id' => $this->batchId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
