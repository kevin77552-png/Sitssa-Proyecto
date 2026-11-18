<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeImportBatch;
use App\Jobs\ApplyImportBatch;
use App\Jobs\RevertImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DispatchImportJobOutbox extends Command
{
    protected $signature = 'imports:dispatch-outbox';

    protected $description = 'Dispatch durable import jobs from the transactional outbox';

    public function handle(): int
    {
        $now = now();
        $messages = DB::transaction(function () use ($now) {
            $messages = DB::table('import_job_outbox')
                ->whereNull('dispatched_at')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('claimed_at')
                        ->orWhere('claimed_at', '<', $now->copy()->subMinutes(5));
                })
                ->orderBy('id')
                ->limit(50)
                ->lockForUpdate()
                ->get();

            foreach ($messages as $message) {
                DB::table('import_job_outbox')->where('id', $message->id)->update([
                    'claimed_at' => $now,
                    'attempts' => $message->attempts + 1,
                    'updated_at' => $now,
                ]);
            }

            return $messages;
        });

        $failed = false;
        foreach ($messages as $message) {
            try {
                match ($message->job_type) {
                    'analyze' => AnalyzeImportBatch::dispatch($message->batch_id),
                    'apply' => ApplyImportBatch::dispatch($message->batch_id),
                    'revert' => $this->dispatchRevert($message),
                    default => throw new RuntimeException('Tipo de job de importación desconocido.'),
                };
            } catch (Throwable $exception) {
                DB::table('import_job_outbox')->where('id', $message->id)->update([
                    'claimed_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);
                Log::error('No se pudo despachar un job de importación desde el outbox', [
                    'outbox_id' => $message->id,
                    'batch_id' => $message->batch_id,
                    'job_type' => $message->job_type,
                    'error' => $exception->getMessage(),
                ]);
                $failed = true;

                continue;
            }

            DB::table('import_job_outbox')->where('id', $message->id)->update([
                'dispatched_at' => now(),
                'claimed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('import_job_outbox')
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', now()->subDays(30))
            ->delete();

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function dispatchRevert(object $message): void
    {
        if ($message->actor_id === null) {
            throw new RuntimeException('El job de reversión no tiene un administrador asociado.');
        }

        RevertImportBatch::dispatch($message->batch_id, (int) $message->actor_id);
    }
}
