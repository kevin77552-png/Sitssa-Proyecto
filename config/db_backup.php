<?php

return [
    'pg_dump' => env('BACKUP_PG_DUMP_PATH', 'pg_dump'),
    'pg_restore' => env('BACKUP_PG_RESTORE_PATH', 'pg_restore'),
    'directory' => env('BACKUP_DIRECTORY') ?: storage_path('app/backups'),
    'copy_to' => env('BACKUP_COPY_PATH'),
    'alert_email' => env('BACKUP_ALERT_EMAIL'),
    'must_contain_table' => 'inventory_items',
    'min_size_bytes' => 10 * 1024,
    'keep_daily' => 7,
    'keep_weekly' => 4,
    'keep_monthly' => 6,
    'timeout_seconds' => 3600,
];
