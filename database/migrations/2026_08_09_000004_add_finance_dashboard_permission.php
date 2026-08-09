<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'finance.dashboard.view'],
            [
                'name' => 'View finance dashboard',
                'module' => 'finance',
                'description' => 'View consolidated finance dashboard and operational finance indicators.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $permissionId = (int) DB::table('permissions')
            ->where('slug', 'finance.dashboard.view')
            ->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', [
                'super-admin',
                'owner-management',
                'branch-manager',
                'cashier',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'role_id' => (int) $roleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'finance.dashboard.view')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
