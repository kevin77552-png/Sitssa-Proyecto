<?php

return [
    'disk' => 'local',
    'directory' => 'imports',
    'chunk_size' => 500,
    'max_file_kilobytes' => 102400,
    'preferred_sheet' => 'JUNIO 2025 (2)',
    'require_backup' => env('IMPORT_REQUIRE_BACKUP', true),
    'dependent_tables' => [
        'spare_requests' => 'inventory_item_id',
    ],
];
