<?php

namespace Tests\Unit;

use App\Jobs\AnalyzeImportBatch;
use App\Jobs\ApplyImportBatch;
use App\Jobs\RevertImportBatch;
use Tests\TestCase;

class QueueRetryAfterTest extends TestCase
{
    public function test_async_queue_retry_windows_exceed_the_import_job_timeout(): void
    {
        $jobs = [
            new AnalyzeImportBatch('batch-id'),
            new ApplyImportBatch('batch-id'),
            new RevertImportBatch('batch-id', 1),
        ];
        $longestTimeout = max(array_map(fn ($job) => $job->timeout, $jobs));

        foreach (['database', 'beanstalkd', 'redis'] as $connection) {
            $this->assertGreaterThan(
                $longestTimeout,
                config("queue.connections.{$connection}.retry_after"),
                "{$connection} retry_after must exceed the import job timeout."
            );
        }
    }
}
