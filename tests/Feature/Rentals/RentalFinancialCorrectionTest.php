<?php

namespace Tests\Feature\Rentals;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalFinancialCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_append_correction_without_mutating_payment_history(): void
    {
        [$user, $rental] = $this->completedRental('super-admin');

        $this->actingAs($user)->post(
            route('rentals.financial-corrections.store', $rental),
            [
                'component' => 'charge',
                'direction' => 'increase',
                'amount' => 25000,
                'reason' => 'Koreksi biaya kerusakan yang terlewat.',
            ],
        )->assertSessionHasNoErrors();

        $rental->refresh();
        $this->assertSame('returned', $rental->status);
        $this->assertSame('125000.00', $rental->total_amount);
        $this->assertSame('25000.00', $rental->balance_due);
        $this->assertDatabaseHas('rental_financial_adjustments', [
            'rental_id' => $rental->id,
            'component' => 'charge',
            'direction' => 'increase',
            'amount' => 25000,
            'balance_before' => 0,
            'balance_after' => 25000,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.completed_financial_corrected',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_super_admin_cannot_correct_completed_rental(): void
    {
        [$user, $rental] = $this->completedRental('branch-manager');

        $this->actingAs($user)->post(
            route('rentals.financial-corrections.store', $rental),
            [
                'component' => 'charge',
                'direction' => 'increase',
                'amount' => 25000,
                'reason' => 'Percobaan koreksi tanpa kewenangan.',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount('rental_financial_adjustments', 0);
        $this->assertSame('0.00', $rental->fresh()->balance_due);
    }

    public function test_correction_is_rejected_for_active_rental_and_invalid_decrease(): void
    {
        [$user, $rental] = $this->completedRental('super-admin');
        $rental->update(['status' => 'active']);

        $this->actingAs($user)->post(
            route('rentals.financial-corrections.store', $rental),
            [
                'component' => 'charge',
                'direction' => 'increase',
                'amount' => 10000,
                'reason' => 'Rental aktif tidak boleh dikoreksi.',
            ],
        )->assertSessionHasErrors('rental');

        $rental->update(['status' => 'returned']);
        $this->actingAs($user)->post(
            route('rentals.financial-corrections.store', $rental),
            [
                'component' => 'payment',
                'direction' => 'decrease',
                'amount' => 150000,
                'reason' => 'Nominal melebihi pembayaran tersedia.',
            ],
        )->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('rental_financial_adjustments', 0);
        $this->assertSame('100000.00', $rental->fresh()->paid_amount);
    }

    /** @return array{User, Rental} */
    private function completedRental(string $roleSlug): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach(
            Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            ['branch_id' => $roleSlug === 'super-admin' ? null : $branch->id, 'assigned_at' => now()],
        );
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-CORRECTION',
            'name' => 'Pelanggan Koreksi',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $rental = Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'RNT-PNG-CORRECTION',
            'status' => 'returned',
            'checked_out_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'returned_at' => now(),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'paid_amount' => 100000,
            'balance_due' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [$user, $rental];
    }
}
