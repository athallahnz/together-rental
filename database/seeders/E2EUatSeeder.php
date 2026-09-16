<?php

namespace Database\Seeders;

use App\Domain\Branches\BranchProvisioner;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class E2EUatSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();
        $this->call(RentalFoundationSeeder::class);

        /** @var array{0: User, 1: User, 2: User, 3: Branch, 4: Branch, 5: array<string, mixed>} $fixtures */
        $fixtures = DB::transaction(function (): array {
            $company = Company::query()->where('code', 'TK')->firstOrFail();
            $ponorogo = Branch::query()
                ->where('company_id', $company->id)
                ->where('code', 'PNG')
                ->firstOrFail();
            $madiun = Branch::query()->create([
                'company_id' => $company->id,
                'code' => 'MDN',
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
                'province' => 'Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'opened_at' => now()->toDateString(),
                'is_active' => true,
            ]);

            app(BranchProvisioner::class)->provision($madiun);

            $admin = $this->createUser('admin', $company, $ponorogo);
            $restricted = $this->createUser('restricted', $company, $ponorogo);
            $branchManager = $this->createUser('branch_manager', $company, $ponorogo);

            $this->assignCompanyRole($admin, $company, 'super-admin');
            $this->assignBranchRole($restricted, $company, $ponorogo, $this->restrictedRole($company));
            $this->assignBranchRole(
                $branchManager,
                $company,
                $ponorogo,
                Role::query()
                    ->where('company_id', $company->id)
                    ->where('slug', 'branch-manager')
                    ->firstOrFail(),
            );

            $this->attachBranch($admin, $ponorogo, true);
            $this->attachBranch($admin, $madiun, false);
            $this->attachBranch($restricted, $ponorogo, true);
            $this->attachBranch($branchManager, $ponorogo, true);

            $this->createBookingFixture($company, $ponorogo, $admin, 'PNG');
            $this->createBookingFixture($company, $madiun, $admin, 'MDN');
            $goldenRental = $this->createGoldenRentalFixture($company, $ponorogo);

            return [$admin, $restricted, $branchManager, $ponorogo, $madiun, $goldenRental];
        });

        $this->writeMetadata(...$fixtures);
    }

    private function guardEnvironment(): void
    {
        $connection = (string) config('database.default');
        $databaseConnection = DB::connection($connection);
        $database = (string) $databaseConnection->getConfig('database');
        $host = mb_strtolower((string) $databaseConnection->getConfig('host'));

        if (
            ! app()->environment('e2e')
            || config('e2e.allow_database_reset') !== true
            || ! in_array($connection, ['mysql', 'mariadb'], true)
            || ! str_ends_with(mb_strtolower($database), '_e2e')
            || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
        ) {
            throw new RuntimeException(
                'E2EUatSeeder may only run against an explicitly enabled local MySQL/MariaDB *_e2e database.',
            );
        }
    }

    private function createUser(string $key, Company $company, Branch $branch): User
    {
        /** @var array{name: string, email: string} $definition */
        $definition = config("e2e.users.{$key}");

        return User::query()->create([
            'company_id' => $company->id,
            'current_branch_id' => $branch->id,
            'name' => $definition['name'],
            'email' => $definition['email'],
            'email_verified_at' => now(),
            'password' => Hash::make((string) config('e2e.password')),
            'status' => 'active',
        ]);
    }

    private function restrictedRole(Company $company): Role
    {
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'E2E Restricted User',
            'slug' => 'e2e-restricted',
            'scope' => 'branch',
            'is_system' => false,
        ]);
        $permissionId = DB::table('permissions')
            ->where('slug', 'branches.switch')
            ->value('id');

        if ($permissionId === null) {
            throw new RuntimeException('The branches.switch permission was not seeded.');
        }

        $role->permissions()->sync([(int) $permissionId]);

        return $role;
    }

    private function assignCompanyRole(User $user, Company $company, string $roleSlug): void
    {
        $role = Role::query()
            ->where('company_id', $company->id)
            ->where('slug', $roleSlug)
            ->firstOrFail();

        $this->insertRoleAssignment($user, $role, null);
    }

    private function assignBranchRole(User $user, Company $company, Branch $branch, Role $role): void
    {
        if ($role->company_id !== $company->id) {
            throw new RuntimeException('E2E role belongs to a different company.');
        }

        $this->insertRoleAssignment($user, $role, $branch->id);
    }

    private function insertRoleAssignment(User $user, Role $role, ?int $branchId): void
    {
        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'assigned_by' => null,
            'assigned_at' => now(),
            'expires_at' => null,
        ]);
    }

    private function attachBranch(User $user, Branch $branch, bool $isDefault): void
    {
        DB::table('branch_user')->insert([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => $isDefault,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBookingFixture(
        Company $company,
        Branch $branch,
        User $creator,
        string $prefix,
    ): void {
        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'registered_branch_id' => $branch->id,
            'customer_number' => "{$prefix}-CUS-E2E-001",
            'name' => "E2E Customer {$branch->code}",
            'phone' => $branch->code === 'PNG' ? '081200000001' : '081200000002',
            'status' => 'active',
            'risk_level' => 'normal',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => "{$prefix}-BKG-E2E-001",
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'deposit_required' => 50000,
            'deposit_paid' => 50000,
            'notes' => 'Deterministic Playwright UAT fixture.',
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    /**
     * @return array{
     *     customer: array{name: string, phone: string, email: string},
     *     product: array{id: int, sku: string, name: string},
     *     asset: array{id: int, code: string},
     *     rate_plan: array{id: int, code: string, name: string},
     *     payment_method: array{id: int, code: string, name: string},
     *     booking: array{starts_at: string, duration_units: int, quantity: int, payment_amount: int, payment_reference: string},
     *     checkout: array{
     *         asset_notes: string,
     *         notes: string,
     *         collateral: array{type: string, number: string, holder_name: string, notes: string}
     *     },
     *     extension: array{
     *         duration_units: int,
     *         payment_amount: int,
     *         payment_reference: string,
     *         notes: string,
     *         payment_notes: string
     *     }
     * }
     */
    private function createGoldenRentalFixture(Company $company, Branch $branch): array
    {
        $ratePlan = RatePlan::query()
            ->where('company_id', $company->id)
            ->whereNull('branch_id')
            ->where('code', '1D')
            ->where('is_active', true)
            ->firstOrFail();
        $paymentMethod = PaymentMethod::query()
            ->where('company_id', $company->id)
            ->where('code', 'TRANSFER')
            ->where('is_active', true)
            ->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $company->id,
            'sku' => 'E2E-GOLDEN-CAMERA',
            'name' => 'E2E Golden Camera',
            'brand' => 'Together Kamera',
            'model' => 'Golden Journey',
            'tracking_type' => 'serialized',
            'replacement_value' => 15000000,
            'is_rentable' => true,
            'is_active' => true,
            'metadata' => ['fixture' => 'golden-rental'],
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $ratePlan->id,
            'amount' => 150000,
            'deposit_amount' => 500000,
            'additional_hour_amount' => 25000,
            'late_fee_amount' => 25000,
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-E2E-GOLDEN-001',
            'serial_number' => 'E2E-GOLDEN-SERIAL-001',
            'status' => 'available',
            'condition' => 'excellent',
            'purchase_price' => 12000000,
            'replacement_value' => 15000000,
            'notes' => 'Dedicated Playwright golden rental fixture.',
            'is_active' => true,
        ]);

        DB::table('branch_inventories')->insert([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'quantity_rented' => 0,
            'quantity_maintenance' => 0,
            'reorder_level' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'customer' => [
                'name' => 'E2E Golden Rental Customer',
                'phone' => '081299990001',
                'email' => 'golden-rental@together-kamera.test',
            ],
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
            ],
            'asset' => [
                'id' => $asset->id,
                'code' => $asset->asset_code,
            ],
            'rate_plan' => [
                'id' => $ratePlan->id,
                'code' => $ratePlan->code,
                'name' => $ratePlan->name,
            ],
            'payment_method' => [
                'id' => $paymentMethod->id,
                'code' => $paymentMethod->code,
                'name' => $paymentMethod->name,
            ],
            'booking' => [
                'starts_at' => now()->addDays(7)->setTime(9, 0)->format('Y-m-d\\TH:i'),
                'duration_units' => 1,
                'quantity' => 1,
                'payment_amount' => 50000,
                'payment_reference' => 'E2E-GOLDEN-DP-001',
            ],
            'checkout' => [
                'asset_notes' => 'Body, battery, charger, and strap verified.',
                'notes' => 'E2E Golden Rental checkout verification.',
                'collateral' => [
                    'type' => 'KTP',
                    'number' => 'E2E-GOLDEN-KTP-001',
                    'holder_name' => 'E2E Golden Rental Customer',
                    'notes' => 'Original identity card held at the Ponorogo counter.',
                ],
            ],
            'extension' => [
                'duration_units' => 1,
                'payment_amount' => 150000,
                'payment_reference' => 'E2E-GOLDEN-EXT-PAY-001',
                'notes' => 'E2E Golden Rental extended by one day.',
                'payment_notes' => 'E2E extension payment via bank transfer.',
            ],
        ];
    }

    /** @param array<string, mixed> $goldenRental */
    private function writeMetadata(
        User $admin,
        User $restricted,
        User $branchManager,
        Branch $ponorogo,
        Branch $madiun,
        array $goldenRental,
    ): void {
        $path = storage_path('framework/testing/e2e-fixtures.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'base_url' => (string) config('e2e.base_url'),
            'password' => (string) config('e2e.password'),
            'users' => [
                'admin' => ['id' => $admin->id, 'email' => $admin->email],
                'restricted' => ['id' => $restricted->id, 'email' => $restricted->email],
                'branch_manager' => ['id' => $branchManager->id, 'email' => $branchManager->email],
            ],
            'branches' => [
                'ponorogo' => ['id' => $ponorogo->id, 'code' => $ponorogo->code, 'name' => $ponorogo->name],
                'madiun' => ['id' => $madiun->id, 'code' => $madiun->code, 'name' => $madiun->name],
            ],
            'golden_rental' => $goldenRental,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
