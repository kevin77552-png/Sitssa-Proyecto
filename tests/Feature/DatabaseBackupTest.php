<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupProcessRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'inventory-backups-'.bin2hex(random_bytes(5));
        config([
            'db_backup.directory' => $this->backupDirectory,
            'db_backup.copy_to' => null,
            'db_backup.min_size_bytes' => 1,
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'pgsql',
            'database.connections.sqlite.host' => '127.0.0.1',
            'database.connections.sqlite.port' => 5432,
            'database.connections.sqlite.database' => 'test_database',
            'database.connections.sqlite.username' => 'test_user',
            'database.connections.sqlite.password' => 'test_password',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->backupDirectory)) {
            foreach (glob($this->backupDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->backupDirectory);
        }

        parent::tearDown();
    }

    public function test_latest_verified_returns_a_recent_backup_that_exists(): void
    {
        $missing = DatabaseBackup::create([
            'filename' => 'missing.dump',
            'path' => $this->backupDirectory.DIRECTORY_SEPARATOR.'missing.dump',
            'status' => 'verified',
            'trigger' => 'manual',
            'verified_at' => now(),
        ]);
        $existingPath = $this->backupDirectory.DIRECTORY_SEPARATOR.'present.dump';
        $this->createFile($existingPath);
        $existing = DatabaseBackup::create([
            'filename' => 'present.dump',
            'path' => $existingPath,
            'status' => 'verified',
            'trigger' => 'manual',
            'verified_at' => now()->subMinute(),
        ]);
        $this->assertNotSame($missing->id, $existing->id);

        $this->assertSame($existing->id, DatabaseBackup::latestVerified(60)?->id);

        $existing->update(['verified_at' => now()->subHours(2)]);
        $this->assertNull(DatabaseBackup::latestVerified(60));
    }

    public function test_manual_backup_command_registers_a_verified_dump(): void
    {
        $this->mock(DatabaseBackupProcessRunner::class, function ($mock): void {
            $mock->shouldReceive('dump')->once()->andReturnUsing(function (array $connection, string $path): void {
                $this->createFile($path, 'verified dump');
            });
            $mock->shouldReceive('verify')->once()->andReturn(['tables' => 3]);
        });

        $this->assertSame(0, Artisan::call('db:backup', ['--trigger' => 'manual']), Artisan::output());

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame('verified', $backup->status);
        $this->assertSame('manual', $backup->trigger);
        $this->assertSame(hash_file('sha256', $backup->path), $backup->sha256);
        $this->assertSame(filesize($backup->path), $backup->size_bytes);
        $this->assertSame(3, $backup->tables_found);
        $this->assertNotNull(DatabaseBackup::latestVerified(60));
    }

    public function test_pre_import_backup_trigger_registers_a_verified_dump(): void
    {
        $this->mock(DatabaseBackupProcessRunner::class, function ($mock): void {
            $mock->shouldReceive('dump')->once()->andReturnUsing(function (array $connection, string $path): void {
                $this->createFile($path, 'verified pre-import dump');
            });
            $mock->shouldReceive('verify')->once()->andReturn(['tables' => 3]);
        });

        $this->assertSame(0, Artisan::call('db:backup', ['--trigger' => 'pre_import']), Artisan::output());

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame('verified', $backup->status);
        $this->assertSame('pre_import', $backup->trigger);
        $this->assertNotNull(DatabaseBackup::latestVerified(60));
    }

    public function test_database_url_credentials_are_decoded_for_the_dump_process(): void
    {
        config(['database.connections.sqlite.url' => 'pgsql://backup_user:p%40ssword@backup.example:5432/inventory?sslmode=require']);
        $capturedConnection = null;
        $this->mock(DatabaseBackupProcessRunner::class, function ($mock) use (&$capturedConnection): void {
            $mock->shouldReceive('dump')->once()->andReturnUsing(function (array $connection, string $path) use (&$capturedConnection): void {
                $capturedConnection = $connection;
                $this->createFile($path, 'verified dump');
            });
            $mock->shouldReceive('verify')->once()->andReturn(['tables' => 1]);
        });

        $this->assertSame(0, Artisan::call('db:backup', ['--trigger' => 'manual']), Artisan::output());
        $this->assertSame('backup_user', $capturedConnection['username']);
        $this->assertSame('p@ssword', $capturedConnection['password']);
        $this->assertSame('require', $capturedConnection['sslmode']);
    }

    public function test_failed_backup_is_recorded_and_partial_file_is_removed(): void
    {
        $this->mock(DatabaseBackupProcessRunner::class, function ($mock): void {
            $mock->shouldReceive('dump')->once()->andReturnUsing(function (array $connection, string $path): void {
                $this->createFile($path, 'partial');
                throw new RuntimeException('pg_dump failed with test_password');
            });
            $mock->shouldNotReceive('verify');
        });

        $this->assertSame(1, Artisan::call('db:backup', ['--trigger' => 'manual']), Artisan::output());

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame('failed', $backup->status);
        $this->assertSame('manual', $backup->trigger);
        $this->assertStringContainsString('pg_dump failed', $backup->error);
        $this->assertStringNotContainsString('test_password', $backup->error);
        $this->assertStringNotContainsString('test_password', Artisan::output());
        $this->assertFileDoesNotExist($backup->path);
    }

    public function test_missing_pg_dump_binary_records_a_failed_backup(): void
    {
        config('db_backup.pg_dump', 'missing-pg-dump-test-binary');

        $this->assertSame(1, Artisan::call('db:backup', ['--trigger' => 'manual']), Artisan::output());

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame('failed', $backup->status);
        $this->assertNotNull($backup->error);
        $this->assertStringNotContainsString('test_password', $backup->error);
        $this->assertFileDoesNotExist($backup->path);
    }

    public function test_scheduled_retention_never_deletes_manual_or_pre_import_backups(): void
    {
        for ($index = 0; $index < 8; $index++) {
            $path = $this->backupDirectory.DIRECTORY_SEPARATOR."scheduled-{$index}.dump";
            $this->createFile($path);
            DatabaseBackup::create([
                'filename' => basename($path),
                'path' => $path,
                'status' => 'verified',
                'trigger' => 'scheduled',
                'verified_at' => now()->subMinutes($index + 1),
            ]);
        }

        $protected = [];
        $path = $this->backupDirectory.DIRECTORY_SEPARATOR.'manual.dump';
        $this->createFile($path);
        $protected[] = DatabaseBackup::create([
            'filename' => basename($path),
            'path' => $path,
            'status' => 'verified',
            'trigger' => 'manual',
            'verified_at' => now()->subYears(2),
        ]);
        $preImportPath = $this->backupDirectory.DIRECTORY_SEPARATOR.'pre-import.dump';
        $this->createFile($preImportPath);
        $protected[] = DatabaseBackup::create([
            'filename' => basename($preImportPath),
            'path' => $preImportPath,
            'status' => 'verified',
            'trigger' => 'pre_import',
            'verified_at' => now()->subYears(2),
        ]);

        $this->mock(DatabaseBackupProcessRunner::class, function ($mock): void {
            $mock->shouldReceive('dump')->once()->andReturnUsing(function (array $connection, string $path): void {
                $this->createFile($path, 'scheduled dump');
            });
            $mock->shouldReceive('verify')->once()->andReturn(['tables' => 1]);
        });

        $this->assertSame(0, Artisan::call('db:backup', ['--trigger' => 'scheduled']), Artisan::output());

        $this->assertSame(1, DatabaseBackup::query()->where('trigger', 'scheduled')->where('status', 'verified')->count());
        $this->assertSame(8, DatabaseBackup::query()->where('trigger', 'scheduled')->where('status', 'purged')->count());
        foreach ($protected as $backup) {
            $this->assertSame('verified', $backup->fresh()->status);
            $this->assertFileExists($backup->path);
        }
    }

    private function createFile(string $path, string $contents = 'dump contents'): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $contents);
    }
}
