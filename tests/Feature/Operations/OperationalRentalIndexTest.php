<?php

namespace Tests\Feature\Operations;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalRentalIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_index_filters_overdue_and_branch_scope(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $origin = Branch::query()->where('code', 'PNG')->firstOrFail();
        $destination = Branch::query()->create([
            'company_id' => $origin->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $origin->company_id,
            'current_branch_id' => $origin->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->branches()->attach($origin->id, ['is_default' => true, 'is_active' => true]);
        $user->branches()->attach($destination->id, ['is_default' => false, 'is_active' => true]);
        $user->roles()->attach($role->id, ['branch_id' => null, 'assigned_at' => now()]);

        $customer = Customer::query()->create([
            'company_id' => $origin->company_id,
            'registered_branch_id' => $origin->id,
            'customer_number' => 'PNG-CUS-OPS',
            'name' => 'Pelanggan Operasional',
            'phone' => '08123456789',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $booking = Booking::query()->create([
            'branch_id' => $origin->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-OPS',
            'status' => 'converted',
            'source' => 'counter',
            'booked_at' => now()->subDays(3),
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        Rental::query()->create([
            'branch_id' => $origin->id,
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-OVERDUE',
            'status' => 'active',
            'checked_out_at' => now()->subDays(2),
            'due_at' => now()->subHours(2),
            'total_amount' => 200000,
            'paid_amount' => 100000,
            'balance_due' => 100000,
        ]);
        Rental::query()->create([
            'branch_id' => $destination->id,
            'customer_id' => $customer->id,
            'rental_number' => 'MDN-RNT-ACTIVE',
            'status' => 'active',
            'checked_out_at' => now(),
            'due_at' => now()->addDay(),
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'balance_due' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('rentals.index', [
                'branch_id' => $origin->id,
                'operational_state' => 'overdue',
                'search' => 'OVERDUE',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rentals/index')
                ->where('summary.overdue', 1)
                ->where('filters.branch_id', $origin->id)
                ->where('filters.operational_state', 'overdue')
                ->has('rentals.data', 1)
                ->where('rentals.data.0.rental_number', 'PNG-RNT-OVERDUE')
                ->where('rentals.data.0.is_overdue', true));
    }

    public function test_booking_index_can_filter_expired_status_per_branch(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach($role->id, ['branch_id' => null, 'assigned_at' => now()]);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-EXPIRED',
            'name' => 'Expired Customer',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'PNG-BKG-EXPIRED',
            'status' => 'expired',
            'source' => 'counter',
            'booked_at' => now()->subDays(5),
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subDays(3),
            'total_amount' => 150000,
        ]);

        $this->actingAs($user)
            ->get(route('bookings.index', [
                'branch_id' => $branch->id,
                'status' => 'expired',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bookings/index')
                ->where('summary.expired', 1)
                ->has('bookings.data', 1)
                ->where('bookings.data.0.status', 'expired'));
    }
}
