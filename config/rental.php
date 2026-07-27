<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Initial Administrator
    |--------------------------------------------------------------------------
    |
    | When this email belongs to an existing user, the foundation seeder can
    | attach that account to the initial company and branch.
    |
    */
    'initial_admin_email' => env('INITIAL_ADMIN_EMAIL'),
    'initial_branch_code' => strtoupper((string) env('INITIAL_BRANCH_CODE', 'PNG')),
];
