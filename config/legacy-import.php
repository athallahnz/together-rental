<?php

return [
    'disk' => env('LEGACY_IMPORT_DISK', 'local'),
    'max_upload_kilobytes' => (int) env('LEGACY_IMPORT_MAX_KB', 102400),
    'chunk_size' => (int) env('LEGACY_IMPORT_CHUNK_SIZE', 500),
    'source_system' => 'RentalV1',
    'branch_code' => strtoupper((string) env(
        'LEGACY_IMPORT_BRANCH_CODE',
        env('INITIAL_BRANCH_CODE', 'PNG'),
    )),
];
