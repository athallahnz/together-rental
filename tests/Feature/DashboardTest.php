<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_operational_scope_can_visit_an_empty_dashboard(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('filters.branch_id', null)
                ->where('visibility.bookings', false)
                ->where('visibility.rentals', false)
                ->where('visibility.finance', false)
                ->where('visibility.assets', false)
                ->where('visibility.customers', false)
                ->has('quickActions', 0)
                ->has('attention', 0)
                ->has('branchPerformance', 0));
    }

    public function test_branch_dashboard_calculates_core_operations_and_prioritized_work_queue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
        $branch = $this->foundation();
        $manager = $this->branchUser($branch, 'branch-manager', 'dashboard.manager@example.test');
        $customer = $this->customer($branch, 'PNG-CUS-DASH-001');
        $booking = Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-DASH-001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now()->subDay(),
            'starts_at' => now()->setTime(13, 0),
            'ends_at' => now()->addDay()->setTime(13, 0),
            'total_amount' => 500000,
            'created_by' => $manager->id,
        ]);
        Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-DASH-OVERDUE',
            'status' => 'active',
            'checked_out_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
            'total_amount' => 400000,
            'paid_amount' => 250000,
            'balance_due' => 150000,
            'created_by' => $manager->id,
        ]);
        Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-DASH-TODAY',
            'status' => 'active',
            'checked_out_at' => now()->subDay(),
            'due_at' => now()->setTime(17, 0),
            'total_amount' => 300000,
            'paid_amount' => 300000,
            'balance_due' => 0,
            'created_by' => $manager->id,
        ]);
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $payment = $this->payment($branch, $method, 500000, 'completed', 'IN');
        $this->payment($branch, $method, 100000, 'void', 'VOID');
        Refund::query()->create([
            'branch_id' => $branch->id,
            'payment_id' => $payment->id,
            'payment_method_id' => $method->id,
            'refund_number' => 'PNG-RFD-DASH-PAID',
            'refund_type' => 'partial',
            'amount' => 50000,
            'status' => 'paid',
            'reason' => 'Regression dashboard.',
            'requested_by' => $manager->id,
            'processed_by' => $manager->id,
            'processed_at' => now(),
        ]);
        $this->assets($branch);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('filters.branch_id', $branch->id)
                ->where('scope.is_company_scope', false)
                ->where('overview.bookings_month', 1)
                ->where('overview.booking_conversion_percent', 0)
                ->where('overview.active_rentals', 2)
                ->where('overview.overdue_rentals', 1)
                ->where('overview.due_today_rentals', 1)
                ->where('overview.receivable_amount', 150000)
                ->where('overview.gross_collections_month', 500000)
                ->where('overview.refunds_month', 50000)
                ->where('overview.net_revenue_month', 450000)
                ->where('overview.asset_total', 3)
                ->where('overview.asset_available', 1)
                ->where('overview.asset_rented', 1)
                ->where('overview.asset_maintenance', 1)
                ->where('overview.asset_utilization_percent', 33.3)
                ->where('overview.customer_total', 1)
                ->where('quickActions.0.key', 'booking')
                ->where('quickActions.1.key', 'direct-rental')
                ->where('attention.0.key', 'rental-overdue')
                ->has('attention', 4)
                ->has('todaySchedule', 2)
                ->where('todaySchedule.0.number', $booking->booking_number)
                ->has('trend', 14)
                ->has('assetHealth', 6)
                ->has('branchPerformance', 1)
                ->has('recentActivity', 5));
    }

    public function test_branch_role_defaults_to_current_branch_and_cannot_query_another_branch(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
        $branch = $this->foundation();
        $other = $this->otherBranch($branch);
        $manager = $this->branchUser($branch, 'branch-manager', 'isolated.dashboard@example.test');
        $pngCustomer = $this->customer($branch, 'PNG-CUS-DASH-ISOLATED');
        $mdnCustomer = $this->customer($other, 'MDN-CUS-DASH-ISOLATED');
        $this->booking($branch, $pngCustomer, 'PNG-BKG-DASH-ISOLATED');
        $this->booking($other, $mdnCustomer, 'MDN-BKG-DASH-ISOLATED');

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.branch_id', $branch->id)
                ->where('overview.bookings_month', 1)
                ->has('branchPerformance', 1)
                ->where('branchPerformance.0.code', $branch->code));

        $this->actingAs($manager)
            ->get(route('dashboard', ['branch_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_company_role_sees_overall_performance_and_can_filter_one_branch(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
        $branch = $this->foundation();
        $other = $this->otherBranch($branch);
        $owner = $this->branchUser($branch, 'owner-management', 'owner.dashboard@example.test', null);
        $pngCustomer = $this->customer($branch, 'PNG-CUS-DASH-OWNER');
        $mdnCustomer = $this->customer($other, 'MDN-CUS-DASH-OWNER');
        $this->booking($branch, $pngCustomer, 'PNG-BKG-DASH-OWNER');
        $this->booking($other, $mdnCustomer, 'MDN-BKG-DASH-OWNER');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.branch_id', null)
                ->where('scope.is_company_scope', true)
                ->where('scope.allow_all_branches', true)
                ->where('overview.bookings_month', 2)
                ->has('branchPerformance', 2));

        $this->actingAs($owner)
            ->get(route('dashboard', ['branch_id' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.branch_id', $other->id)
                ->where('scope.is_company_scope', false)
                ->where('overview.bookings_month', 1)
                ->has('branchPerformance', 1)
                ->where('branchPerformance.0.code', 'MDN'));
    }

    public function test_dashboard_visibility_and_quick_actions_follow_role_permissions(): void
    {
        $branch = $this->foundation();
        $inventory = $this->branchUser(
            $branch,
            'inventory-staff',
            'inventory.dashboard@example.test',
        );

        $this->actingAs($inventory)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visibility.bookings', false)
                ->where('visibility.rentals', false)
                ->where('visibility.finance', false)
                ->where('visibility.assets', true)
                ->where('visibility.customers', false)
                ->where('quickActions.0.key', 'stock-opname')
                ->where('quickActions.1.key', 'maintenance')
                ->where('quickActions.2.key', 'notification')
                ->has('todaySchedule', 0)
                ->has('recentActivity', 0));
    }

    private function foundation(): Branch
    {
        $this->seed(RentalFoundationSeeder::class);

        return Branch::query()->where('code', 'PNG')->firstOrFail();
    }

    private function otherBranch(Branch $branch): Branch
    {
        return Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function branchUser(
        Branch $branch,
        string $roleSlug,
        string $email,
        ?int $roleBranchId = null,
    ): User {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
        $assignmentBranchId = func_num_args() >= 4
            ? $roleBranchId
            : ($role->scope === 'company' ? null : $branch->id);
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'email' => $email,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => $assignmentBranchId,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function customer(Branch $branch, string $number): Customer
    {
        return Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => $number,
            'name' => 'Pelanggan '.$number,
            'phone' => '081234567890',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
    }

    private function booking(Branch $branch, Customer $customer, string $number): Booking
    {
        return Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => $number,
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'total_amount' => 250000,
        ]);
    }

    private function payment(
        Branch $branch,
        PaymentMethod $method,
        float $amount,
        string $status,
        string $suffix,
    ): Payment {
        return Payment::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'payment_number' => "{$branch->code}-PAY-DASH-{$suffix}",
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'status' => $status,
            'amount' => $amount,
            'paid_at' => now(),
        ]);
    }

    private function assets(Branch $branch): void
    {
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-DASH-001',
            'name' => 'Kamera Dashboard Test',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);

        foreach (['available', 'rented', 'maintenance'] as $index => $status) {
            Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => 'PNG-DASH-'.($index + 1),
                'status' => $status,
                'condition' => 'good',
                'is_active' => true,
            ]);
        }
    }
}
