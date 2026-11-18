<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupProcessRunner
{
    public function dump(array $connection, string $path): void
    {
        $process = new Process([
            config('db_backup.pg_dump'),
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-F', 'c',
            '--no-password',
            '-f', $path,
            (string) $connection['database'],
        ], null, array_filter([
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
            'PGSSLMODE' => $connection['sslmode'] ?? null,
        ], static fn ($value) => $value !== null), null, config('db_backup.timeout_seconds'));

        $process->run();
        if (! $process->isSuccessful()) {
            $diagnostics = array_filter([
                trim($process->getErrorOutput()) !== '' ? 'stderr: '.trim($process->getErrorOutput()) : null,
                trim($process->getOutput()) !== '' ? 'stdout: '.trim($process->getOutput()) : null,
            ]);
            $exitCode = $process->getExitCode();
            $exitCode = $exitCode === null ? 'desconocido' : (string) $exitCode;

            throw new RuntimeException(sprintf(
                'pg_dump falló (código de salida: %s): %s',
                $exitCode,
                $diagnostics !== [] ? implode(' | ', $diagnostics) : 'sin salida del proceso',
            ));
        }
    }

    public function verify(string $path): array
    {
        if (! is_file($path) || filesize($path) < config('db_backup.min_size_bytes')) {
            throw new RuntimeException('El archivo de respaldo no existe o es demasiado pequeño.');
        }

        $process = new Process([config('db_backup.pg_restore'), '--list', $path], null, null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pg_restore --list no pudo leer el respaldo: '.trim($process->getErrorOutput()));
        }

        $list = $process->getOutput();
        $requiredTable = config('db_backup.must_contain_table');
        if ($requiredTable && ! str_contains($list, $requiredTable)) {
            throw new RuntimeException("El respaldo no contiene la tabla {$requiredTable}.");
        }

        return ['tables' => preg_match_all('/\bTABLE\b(?! DATA)/', $list)];
    }
}
