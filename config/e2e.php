<?php

return [
    'allow_database_reset' => env('E2E_ALLOW_DATABASE_RESET', false),
    'base_url' => env('E2E_BASE_URL', 'http://127.0.0.1:8010'),
    'password' => env('E2E_USER_PASSWORD', 'Playwright#2026'),
    'users' => [
        'admin' => [
            'name' => 'E2E Super Administrator',
            'email' => env('E2E_ADMIN_EMAIL', 'e2e.admin@together-kamera.test'),
        ],
        'restricted' => [
            'name' => 'E2E Restricted User',
            'email' => env('E2E_RESTRICTED_EMAIL', 'e2e.restricted@together-kamera.test'),
        ],
        'branch_manager' => [
            'name' => 'E2E Branch Manager',
            'email' => env('E2E_BRANCH_MANAGER_EMAIL', 'e2e.branch-manager@together-kamera.test'),
        ],
    ],
];
