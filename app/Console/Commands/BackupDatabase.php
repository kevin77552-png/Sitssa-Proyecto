<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupProcessRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--trigger=manual : scheduled | manual | pre_import}
                            {--user= : ID del usuario que lo solicita (opcional)}';

    protected $description = 'Genera, verifica y registra un respaldo pg_dump de PostgreSQL';

    public function handle(DatabaseBackupProcessRunner $processes): int
    {
        $trigger = $this->option('trigger');
        if (! in_array($trigger, ['scheduled', 'manual', 'pre_import'], true)) {
            $this->error('El trigger debe ser scheduled, manual o pre_import.');

            return self::FAILURE;
        }

        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");
        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error("Este comando solo funciona con PostgreSQL (conexión actual: {$connectionName}).");

            return self::FAILURE;
        }

        $userOption = $this->option('user');
        $userId = $userOption === null ? null : filter_var($userOption, FILTER_VALIDATE_INT);
        if ($userOption !== null && ($userId === false || ! \App\Models\User::whereKey($userId)->exists())) {
            $this->error('El usuario indicado no existe.');

            return self::FAILURE;
        }

        $backup = null;
        $path = null;
        $copyPath = null;
        $connectionFingerprint = null;

        try {
            $connection = $this->connectionOptions($connection);
            $connectionFingerprint = hash('sha256', implode('|', [
                (string) $connection['host'],
                (string) $connection['port'],
                (string) $connection['database'],
                (string) $connection['username'],
                (string) ($connection['sslmode'] ?? ''),
            ]));
            $directory = config('db_backup.directory');
            File::ensureDirectoryExists($directory, 0700);
            $directory = realpath($directory) ?: throw new RuntimeException('No se pudo resolver el directorio de respaldos.');
            $this->assertOutsidePublicDirectory($directory);
            $this->restrictDirectoryPermissions($directory);

            $databaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', $connection['database']) ?: 'database';
            $filename = sprintf('%s_%s_%s.dump', $databaseName, now()->format('Ymd_His'), bin2hex(random_bytes(4)));
            $path = $directory.DIRECTORY_SEPARATOR.$filename;

            $backup = DatabaseBackup::create([
                'filename' => $filename,
                'path' => $path,
                'status' => 'running',
                'trigger' => $trigger,
                'created_by' => $userId ?: null,
            ]);

            $processes->dump($connection, $path);
            $this->restrictFilePermissions($path);
            $info = $processes->verify($path);
            $size = filesize($path);
            $sha256 = hash_file('sha256', $path);
            if ($size === false || $sha256 === false) {
                throw new RuntimeException('No se pudo calcular el tamaño o hash del respaldo.');
            }

            $copyPath = $this->copyOffsite($path);
            $backup->update([
                'status' => 'verified',
                'size_bytes' => $size,
                'sha256' => $sha256,
                'tables_found' => $info['tables'],
                'verified_at' => now(),
                'copy_path' => $copyPath,
            ]);

            $this->info("Respaldo verificado: {$path} (".round($size / 1048576, 2).' MB)');
            Log::info('Respaldo de BD verificado', [
                'archivo' => $filename,
                'trigger' => $trigger,
                'php_sapi' => PHP_SAPI,
                'php_binary' => PHP_BINARY,
                'working_directory' => getcwd(),
                'pg_dump_binary' => config('db_backup.pg_dump'),
                'connection_fingerprint' => $connectionFingerprint,
            ]);

            if ($trigger === 'scheduled') {
                try {
                    $this->purgeOldScheduled();
                } catch (Throwable $exception) {
                    Log::error('El respaldo se verificó, pero falló la retención programada', [
                        'backup_id' => $backup->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($path !== null && is_file($path) && ! unlink($path)) {
                Log::error('No se pudo eliminar un respaldo incompleto', ['archivo' => basename($path)]);
            }
            if ($copyPath !== null && is_file($copyPath) && ! unlink($copyPath)) {
                Log::error('No se pudo eliminar una copia externa incompleta', ['archivo' => basename($copyPath)]);
            }

            $message = $this->redactSecrets($exception->getMessage(), $connection ?? []);
            if ($backup !== null) {
                $backup->update(['status' => 'failed', 'error' => mb_substr($message, 0, 2000)]);
            }

            Log::critical('Respaldo de BD falló', [
                'archivo' => $backup?->filename,
                'trigger' => $trigger,
                'error' => $message,
                'php_sapi' => PHP_SAPI,
                'php_binary' => PHP_BINARY,
                'working_directory' => getcwd(),
                'pg_dump_binary' => config('db_backup.pg_dump'),
                'connection_fingerprint' => $connectionFingerprint,
            ]);
            $this->sendFailureAlert($message);
            $this->error('Respaldo fallido: '.$message);

            return self::FAILURE;
        }
    }

    private function connectionOptions(array $connection): array
    {
        if (! empty($connection['url'])) {
            $parts = parse_url($connection['url']);
            if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
                throw new RuntimeException('DB_URL no contiene una conexión PostgreSQL válida.');
            }

            parse_str($parts['query'] ?? '', $query);

            return [
                'host' => $parts['host'],
                'port' => $parts['port'] ?? 5432,
                'username' => rawurldecode($parts['user'] ?? ''),
                'password' => rawurldecode($parts['pass'] ?? ''),
                'database' => rawurldecode(ltrim($parts['path'], '/')),
                'sslmode' => $query['sslmode'] ?? null,
            ];
        }

        if (empty($connection['host']) || empty($connection['database']) || empty($connection['username'])) {
            throw new RuntimeException('La conexión PostgreSQL requiere host, base de datos y usuario.');
        }

        return [
            'host' => $connection['host'],
            'port' => $connection['port'] ?? 5432,
            'username' => $connection['username'],
            'password' => $connection['password'] ?? '',
            'database' => $connection['database'],
            'sslmode' => $connection['sslmode'] ?? null,
        ];
    }

    private function copyOffsite(string $path): ?string
    {
        $destination = config('db_backup.copy_to');
        if (! $destination) {
            return null;
        }

        File::ensureDirectoryExists($destination, 0700);
        $destination = realpath($destination) ?: throw new RuntimeException('No se pudo resolver la ruta de copia externa.');
        $this->assertOutsidePublicDirectory($destination);
        $this->restrictDirectoryPermissions($destination);
        if ($destination === realpath(dirname($path))) {
            throw new RuntimeException('La copia externa debe estar en un directorio diferente al respaldo local.');
        }

        $target = $destination.DIRECTORY_SEPARATOR.basename($path);
        try {
            if (! copy($path, $target)) {
                throw new RuntimeException('No se pudo copiar el respaldo a la ruta externa configurada.');
            }

            $this->restrictFilePermissions($target);
            $copyHash = hash_file('sha256', $target);
            $sourceHash = hash_file('sha256', $path);
            if ($copyHash === false || $sourceHash === false || ! hash_equals($sourceHash, $copyHash)) {
                throw new RuntimeException('La copia externa no coincide con el respaldo original.');
            }
        } catch (Throwable $exception) {
            if (is_file($target) && ! unlink($target)) {
                Log::error('No se pudo eliminar una copia externa incompleta', ['archivo' => basename($target)]);
            }

            throw $exception;
        }

        return $target;
    }

    private function purgeOldScheduled(): void
    {
        $backups = DatabaseBackup::query()
            ->where('status', 'verified')
            ->where('trigger', 'scheduled')
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->get();
        $keep = [];

        foreach ([
            [config('db_backup.keep_daily'), fn ($date) => $date->format('Y-m-d')],
            [config('db_backup.keep_weekly'), fn ($date) => $date->format('o-W')],
            [config('db_backup.keep_monthly'), fn ($date) => $date->format('Y-m')],
        ] as [$limit, $periodKey]) {
            $periods = [];
            foreach ($backups as $candidate) {
                $period = $periodKey($candidate->verified_at);
                if (! isset($periods[$period]) && count($periods) < $limit) {
                    $periods[$period] = true;
                    $keep[$candidate->id] = true;
                }
            }
        }

        foreach ($backups as $candidate) {
            if (isset($keep[$candidate->id]) || ! $this->deleteBackupFiles($candidate)) {
                continue;
            }

            $candidate->update(['status' => 'purged']);
        }
    }

    private function deleteBackupFiles(DatabaseBackup $backup): bool
    {
        $paths = array_filter([$backup->path, $backup->copy_path]);
        $allowedDirectories = array_filter([
            realpath(config('db_backup.directory')),
            config('db_backup.copy_to') ? realpath(config('db_backup.copy_to')) : null,
        ]);

        foreach ($paths as $path) {
            $resolved = realpath($path);
            if ($resolved === false) {
                continue;
            }

            if (! collect($allowedDirectories)->contains(fn ($directory) => $this->isPathWithin($resolved, $directory))) {
                Log::warning('Se omitió eliminar un respaldo fuera de las rutas configuradas', ['backup_id' => $backup->id]);

                return false;
            }

            if (is_file($resolved) && ! unlink($resolved)) {
                Log::error('No se pudo eliminar un respaldo programado', ['backup_id' => $backup->id]);

                return false;
            }
        }

        return true;
    }

    private function assertOutsidePublicDirectory(string $directory): void
    {
        $publicDirectory = realpath(public_path());
        if ($publicDirectory !== false && $this->isPathWithin($directory, $publicDirectory)) {
            throw new RuntimeException('El directorio de respaldos debe estar fuera de public/.');
        }
    }

    private function isPathWithin(string $path, string $directory): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $directory = rtrim(str_replace('\\', '/', $directory), '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $directory = strtolower($directory);
        }

        return $path === $directory || str_starts_with($path, $directory.'/');
    }

    private function restrictFilePermissions(string $path): void
    {
        if (DIRECTORY_SEPARATOR !== '\\' && ! chmod($path, 0600)) {
            throw new RuntimeException('No se pudieron restringir los permisos del respaldo.');
        }
    }

    private function restrictDirectoryPermissions(string $path): void
    {
        if (DIRECTORY_SEPARATOR !== '\\' && ! chmod($path, 0700)) {
            throw new RuntimeException('No se pudieron restringir los permisos del directorio de respaldos.');
        }
    }

    private function redactSecrets(string $message, array $connection): string
    {
        $password = $connection['password'] ?? null;

        return $password ? str_replace($password, '[redacted]', $message) : $message;
    }

    private function sendFailureAlert(string $message): void
    {
        $recipient = config('db_backup.alert_email');
        if (! $recipient) {
            return;
        }

        try {
            Mail::raw('El respaldo de la base de datos falló el '.now().":\n\n{$message}", fn ($mail) => $mail
                ->to($recipient)
                ->subject('ALERTA: respaldo de BD fallido'));
        } catch (Throwable $exception) {
            Log::error('No se pudo enviar la alerta de respaldo', ['error' => $exception->getMessage()]);
        }
    }
}
