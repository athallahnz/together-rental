<?php

namespace Database\Seeders;

use App\Domain\Catalog\Intelligence\CatalogTextNormalizer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RentalFoundationSeeder extends Seeder
{
    /**
     * Seed the organization, initial branch, access control, and operational defaults.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $branchCode = (string) config('rental.initial_branch_code', 'PNG');

            DB::table('companies')->updateOrInsert(
                ['code' => 'TK'],
                [
                    'name' => 'Together Kamera',
                    'legal_name' => 'Together Kamera',
                    'timezone' => 'Asia/Jakarta',
                    'currency' => 'IDR',
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');

            DB::table('branches')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $branchCode],
                [
                    'name' => 'Together Kamera Ponorogo',
                    'city' => 'Ponorogo',
                    'province' => 'Jawa Timur',
                    'timezone' => 'Asia/Jakarta',
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $branchId = (int) DB::table('branches')
                ->where('company_id', $companyId)
                ->where('code', $branchCode)
                ->value('id');

            $this->seedBranchSettings($branchId, $now);
            $this->seedAccessControl($companyId, $now);
            $this->seedOperationalDefaults($companyId, $branchId, $branchCode, $now);
            $this->seedCatalogIntelligence($companyId, $now);
            $this->attachInitialAdministrator($companyId, $branchId, $now);
        });
    }

    private function seedBranchSettings(int $branchId, mixed $now): void
    {
        $settings = [
            'currency' => ['string', 'IDR', true],
            'default_timezone' => ['string', 'Asia/Jakarta', true],
            'legacy_source_system' => ['string', 'RentalV1', false],
            'require_customer_identity' => ['boolean', true, false],
            'allow_cross_branch_return' => ['boolean', false, false],
        ];

        foreach ($settings as $key => [$type, $value, $isPublic]) {
            DB::table('branch_settings')->updateOrInsert(
                ['branch_id' => $branchId, 'key' => $key],
                [
                    'value_type' => $type,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => $isPublic,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function seedAccessControl(int $companyId, mixed $now): void
    {
        $permissions = [
            ['company.view', 'View company', 'company'],
            ['company.manage', 'Manage company', 'company'],
            ['branches.view', 'View branches', 'branches'],
            ['branches.manage', 'Manage branches', 'branches'],
            ['branches.switch', 'Switch active branch', 'branches'],
            ['users.view', 'View users', 'users'],
            ['users.manage', 'Manage users', 'users'],
            ['roles.view', 'View roles and permissions', 'roles'],
            ['roles.manage', 'Manage roles and permissions', 'roles'],
            ['customers.view', 'View customers', 'customers'],
            ['customers.create', 'Create customers', 'customers'],
            ['customers.update', 'Update customers', 'customers'],
            ['customers.delete', 'Archive customers', 'customers'],
            ['customers.verify', 'Verify customer identities', 'customers'],
            ['customers.loyalty', 'Manage customer loyalty points', 'customers'],
            ['products.view', 'View product catalog', 'products'],
            ['products.manage', 'Manage product catalog', 'products'],
            ['assets.view', 'View rental assets', 'assets'],
            ['assets.manage', 'Manage rental assets', 'assets'],
            ['assets.inspect', 'Inspect asset condition', 'assets'],
            ['bookings.view', 'View bookings', 'bookings'],
            ['bookings.create', 'Create bookings', 'bookings'],
            ['bookings.update', 'Update bookings', 'bookings'],
            ['bookings.cancel', 'Cancel bookings', 'bookings'],
            ['rentals.view', 'View rentals', 'rentals'],
            ['rentals.create', 'Create rental checkout', 'rentals'],
            ['rentals.update', 'Update rentals', 'rentals'],
            ['rentals.extend', 'Extend rentals', 'rentals'],
            ['rentals.return', 'Process rental returns', 'rentals'],
            ['rentals.correct_completed', 'Correct completed rental financials', 'rentals'],
            ['rentals.reopen_return', 'Reopen completed rental return', 'rentals'],
            ['payments.view', 'View payments', 'payments'],
            ['payments.create', 'Create payments', 'payments'],
            ['payments.void', 'Void payments', 'payments'],
            ['refunds.manage', 'Manage refunds', 'payments'],
            ['cash.view', 'View cash sessions', 'cash'],
            ['cash.manage', 'Manage cash sessions', 'cash'],
            ['transfers.view', 'View branch transfers', 'transfers'],
            ['transfers.create', 'Create branch transfers', 'transfers'],
            ['transfers.approve', 'Approve branch transfers', 'transfers'],
            ['transfers.receive', 'Receive branch transfers', 'transfers'],
            ['maintenance.view', 'View maintenance', 'maintenance'],
            ['maintenance.manage', 'Manage maintenance', 'maintenance'],
            ['reports.view', 'View reports', 'reports'],
            ['reports.export', 'Export reports', 'reports'],
            ['imports.view', 'View legacy imports', 'imports'],
            ['imports.upload', 'Upload legacy imports', 'imports'],
            ['imports.validate', 'Validate and map legacy imports', 'imports'],
            ['imports.execute', 'Execute legacy imports', 'imports'],
            ['audit.view', 'View audit trail', 'audit'],
        ];

        foreach ($permissions as [$slug, $name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => $module,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $roles = [
            'super-admin' => ['Super Admin', 'company'],
            'owner-management' => ['Owner / Management', 'company'],
            'branch-manager' => ['Branch Manager', 'branch'],
            'rental-operator' => ['Rental Operator', 'branch'],
            'cashier' => ['Cashier', 'branch'],
            'inventory-staff' => ['Inventory Staff', 'branch'],
        ];

        foreach ($roles as $slug => [$name, $scope]) {
            DB::table('roles')->updateOrInsert(
                ['company_id' => $companyId, 'slug' => $slug],
                [
                    'name' => $name,
                    'scope' => $scope,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $rolePermissions = [
            'super-admin' => ['*'],
            'owner-management' => [
                'company.view', 'branches.view', 'branches.switch', 'users.view',
                'roles.view', 'customers.view', 'products.view', 'assets.view',
                'bookings.view', 'rentals.view', 'payments.view', 'cash.view',
                'transfers.view', 'maintenance.view', 'reports.view',
                'reports.export', 'imports.view', 'audit.view',
            ],
            'branch-manager' => [
                'branches.view', 'branches.switch', 'users.view', 'customers.view',
                'customers.create', 'customers.update', 'customers.verify',
                'customers.loyalty',
                'products.view', 'products.manage', 'assets.view', 'assets.manage', 'assets.inspect',
                'bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel',
                'rentals.view', 'rentals.create', 'rentals.update', 'rentals.extend',
                'rentals.return', 'payments.view', 'payments.create', 'payments.void',
                'refunds.manage', 'cash.view', 'cash.manage', 'transfers.view',
                'transfers.create', 'transfers.approve', 'transfers.receive',
                'maintenance.view', 'maintenance.manage', 'reports.view',
                'reports.export', 'imports.view',
            ],
            'rental-operator' => [
                'branches.switch', 'customers.view', 'customers.create',
                'customers.update', 'customers.verify', 'products.view', 'assets.view',
                'customers.loyalty',
                'assets.inspect', 'bookings.view', 'bookings.create', 'bookings.update',
                'bookings.cancel', 'rentals.view', 'rentals.create', 'rentals.update',
                'rentals.extend', 'rentals.return', 'payments.view', 'payments.create',
            ],
            'cashier' => [
                'branches.switch', 'customers.view', 'bookings.view', 'rentals.view',
                'payments.view', 'payments.create', 'cash.view', 'cash.manage',
                'reports.view',
            ],
            'inventory-staff' => [
                'branches.switch', 'products.view', 'products.manage', 'assets.view',
                'assets.manage', 'assets.inspect', 'transfers.view', 'transfers.create',
                'transfers.receive', 'maintenance.view', 'maintenance.manage',
                'reports.view',
            ],
        ];

        $allPermissionIds = DB::table('permissions')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $roleId = (int) DB::table('roles')
                ->where('company_id', $companyId)
                ->where('slug', $roleSlug)
                ->value('id');

            $permissionIds = $permissionSlugs === ['*']
                ? $allPermissionIds
                : DB::table('permissions')
                    ->whereIn('slug', $permissionSlugs)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

            DB::table('permission_role')->where('role_id', $roleId)->delete();

            DB::table('permission_role')->insert(array_map(
                fn (int $permissionId): array => [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $permissionIds,
            ));
        }
    }

    private function seedOperationalDefaults(
        int $companyId,
        int $branchId,
        string $branchCode,
        mixed $now,
    ): void {
        $ratePlans = [
            ['6H', '6 Hours', 'hour', 6],
            ['12H', '12 Hours', 'hour', 12],
            ['1D', '1 Day', 'day', 1],
        ];

        foreach ($ratePlans as [$code, $name, $unit, $value]) {
            DB::table('rate_plans')->updateOrInsert(
                ['company_id' => $companyId, 'branch_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'duration_unit' => $unit,
                    'duration_value' => $value,
                    'grace_period_minutes' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ([
            ['CASH', 'Cash', 'cash', false],
            ['TRANSFER', 'Bank Transfer', 'bank_transfer', true],
            ['QRIS', 'QRIS', 'qris', true],
            ['CARD', 'Debit / Credit Card', 'card', true],
        ] as [$code, $name, $type, $requiresReference]) {
            DB::table('payment_methods')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'requires_reference' => $requiresReference,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ([
            ['RENTAL', 'Rental Revenue', 'income'],
            ['DEPOSIT', 'Rental Deposit', 'liability'],
            ['LATE-FEE', 'Late Fee', 'income'],
            ['DAMAGE', 'Damage Charge', 'income'],
            ['REFUND', 'Customer Refund', 'expense'],
            ['OPERATING', 'Operating Expense', 'expense'],
        ] as [$code, $name, $type]) {
            DB::table('financial_categories')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('cash_registers')->updateOrInsert(
            ['branch_id' => $branchId, 'code' => 'MAIN'],
            [
                'name' => 'Main Cash Register',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ([
            'customer' => 'CUS',
            'booking' => 'BKG',
            'rental' => 'RNT',
            'extension' => 'EXT',
            'return' => 'RET',
            'payment' => 'PAY',
            'refund' => 'RFD',
            'transfer' => 'TRF',
            'maintenance' => 'MNT',
        ] as $documentType => $shortCode) {
            $scope = [
                'branch_id' => $branchId,
                'document_type' => $documentType,
                'year' => (int) now()->format('Y'),
                'month' => $documentType === 'customer'
                    ? 0
                    : (int) now()->format('m'),
            ];
            DB::table('number_sequences')->insertOrIgnore([
                ...$scope,
                'prefix' => "{$branchCode}-{$shortCode}",
                'last_number' => 0,
                'padding' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('number_sequences')
                ->where($scope)
                ->update([
                    'prefix' => "{$branchCode}-{$shortCode}",
                    'padding' => 6,
                    'updated_at' => $now,
                ]);
        }
    }

    private function seedCatalogIntelligence(int $companyId, mixed $now): void
    {
        $normalizer = app(CatalogTextNormalizer::class);
        $dictionary = [
            'Apple' => ['APPLE', 'IPHONE', 'IPHON'],
            'Asus' => ['ASUS'],
            'BenQ' => ['BENQ'],
            'Boya' => ['BOYA'],
            'Canon' => ['CANON'],
            'DJI' => ['DJI', 'MAVIC', 'OSMO'],
            'Fujifilm' => ['FUJIFILM', 'FUJI FILM', 'FUJI', 'FUJIFULM'],
            'Godox' => ['GODOX'],
            'GoPro' => ['GOPRO', 'GO PRO'],
            'InFocus' => ['INFOCUS'],
            'Insta360' => ['INSTA360', 'INSTA 360'],
            'Lenovo' => ['LENOVO'],
            'Meike' => ['MEIKE'],
            'Nikon' => ['NIKON'],
            'Panasonic' => ['PANASONIC', 'LUMIX'],
            'Rode' => ['RODE'],
            'Samyang' => ['SAMYANG'],
            'Saramonic' => ['SARAMONIC'],
            'Sigma' => ['SIGMA'],
            'Somita' => ['SOMITA'],
            'Sony' => ['SONY'],
            'Tamron' => ['TAMRON'],
            'Tokina' => ['TOKINA'],
            'Viltrox' => ['VILTROX'],
            'Zeiss' => ['ZEISS', 'CARL ZEISS'],
            'Zhiyun' => ['ZHIYUN'],
            '7Artisans' => ['7ARTISANS', '7 ARTISANS', '7ARTISAN', '7 ARTISAN'],
        ];

        foreach ($dictionary as $name => $aliases) {
            $normalizedName = $normalizer->normalize($name);
            DB::table('catalog_brands')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'normalized_name' => $normalizedName,
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $brandId = (int) DB::table('catalog_brands')
                ->where('company_id', $companyId)
                ->where('normalized_name', $normalizedName)
                ->value('id');

            foreach (array_unique([$name, ...$aliases]) as $alias) {
                DB::table('catalog_brand_aliases')->updateOrInsert(
                    [
                        'catalog_brand_id' => $brandId,
                        'normalized_alias' => $normalizer->normalize($alias),
                    ],
                    [
                        'alias' => $alias,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    private function attachInitialAdministrator(int $companyId, int $branchId, mixed $now): void
    {
        $email = config('rental.initial_admin_email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $user = User::query()->where('email', trim($email))->first();

        if ($user === null) {
            $this->command->warn("INITIAL_ADMIN_EMAIL [{$email}] was not found; organization data was seeded without assigning an administrator.");

            return;
        }

        $roleId = (int) DB::table('roles')
            ->where('company_id', $companyId)
            ->where('slug', 'super-admin')
            ->value('id');

        $user->forceFill([
            'company_id' => $companyId,
            'current_branch_id' => $branchId,
            'status' => 'active',
        ])->save();

        DB::table('branch_user')->updateOrInsert(
            ['branch_id' => $branchId, 'user_id' => $user->id],
            [
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('role_user')->updateOrInsert(
            ['role_id' => $roleId, 'user_id' => $user->id, 'branch_id' => null],
            [
                'assigned_by' => $user->id,
                'assigned_at' => $now,
                'expires_at' => null,
            ],
        );
    }
}
