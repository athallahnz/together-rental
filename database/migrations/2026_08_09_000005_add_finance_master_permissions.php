<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            [
                'finance.masters.view',
                'View finance master data',
                'View payment methods, financial categories, cash registers, and cash-session status.',
            ],
            [
                'finance.payment_methods.manage',
                'Manage payment methods',
                'Create, update, activate, and deactivate company payment methods.',
            ],
            [
                'finance.categories.manage',
                'Manage financial categories',
                'Create, update, activate, and deactivate company financial categories.',
            ],
            [
                'finance.cash_registers.manage',
                'Manage cash registers',
                'Create, update, activate, and deactivate branch cash registers.',
            ],
        ];

        foreach ($permissions as [$slug, $name, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'finance',
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $roleDefaults = [
            'super-admin' => array_column($permissions, 0),
            'owner-management' => array_column($permissions, 0),
            'branch-manager' => [
                'finance.masters.view',
                'finance.cash_registers.manage',
            ],
            'cashier' => ['finance.masters.view'],
        ];

        foreach ($roleDefaults as $roleSlug => $slugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        [
                            'permission_id' => (int) $permissionId,
                            'role_id' => (int) $roleId,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $this->permissionSlugs())
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    /** @return list<string> */
    private function permissionSlugs(): array
    {
        return [
            'finance.masters.view',
            'finance.payment_methods.manage',
            'finance.categories.manage',
            'finance.cash_registers.manage',
        ];
    }
};
