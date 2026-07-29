<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MaintenanceOrder;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalReturn;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalOperationalCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_reopen_and_refinalize_return_without_duplicating_financials(): void
    {
        [$user, $rental, $asset, $return] = $this->returnedRental();
        $originalPaid = $rental->paid_amount;
        $originalBalance = $rental->balance_due;
        $replacement = Asset::query()->create([
            'product_id' => $asset->product_id,
            'owning_branch_id' => $asset->owning_branch_id,
            'current_branch_id' => $asset->current_branch_id,
            'asset_code' => 'CAM-OP-CORRECTION-002',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(
            route('rentals.operational-corrections.store', $rental),
            [
                'rental_return_id' => $return->id,
                'reason' => 'Kondisi unit pada pemeriksaan awal tercatat keliru.',
            ],
        )->assertRedirect(route('rentals.return.create', $rental));

        $rental->refresh();
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();
        $this->assertSame('correction_pending', $rental->status);
        $this->assertSame('out', $unit->status);
        $this->assertSame('rented', $asset->fresh()->status);
        $this->assertSame(0, MaintenanceOrder::query()->where('status', 'reported')->count());
        $this->assertDatabaseHas('rental_operational_corrections', [
            'rental_id' => $rental->id,
            'original_return_id' => $return->id,
            'status' => 'open',
        ]);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'replacement_asset_id' => $replacement->id,
                'condition' => 'good',
                'notes' => 'Kondisi dikoreksi menjadi baik.',
            ]],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('rentals.show', $rental));

        $rental->refresh();
        $this->assertSame('returned', $rental->status);
        $this->assertSame($originalPaid, $rental->paid_amount);
        $this->assertSame($originalBalance, $rental->balance_due);
        $this->assertSame('available', $asset->fresh()->status);
        $this->assertSame('available', $replacement->fresh()->status);
        $this->assertSame($replacement->id, $unit->fresh()->asset_id);
        $this->assertSame('superseded', $return->fresh()->status);
        $this->assertDatabaseCount('rental_returns', 2);
        $this->assertDatabaseHas('rental_operational_corrections', [
            'rental_id' => $rental->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.return_reopened',
        ]);
    }

    public function test_reopen_is_rejected_atomically_after_maintenance_has_started(): void
    {
        [$user, $rental, $asset, $return] = $this->returnedRental();
        MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->firstOrFail()
            ->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($user)->post(
            route('rentals.operational-corrections.store', $rental),
            [
                'rental_return_id' => $return->id,
                'reason' => 'Perlu mengoreksi kondisi unit setelah pengembalian.',
            ],
        )->assertSessionHasErrors('rental_return_id');

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame('maintenance', $asset->fresh()->status);
        $this->assertDatabaseCount('rental_operational_corrections', 0);
    }

    public function test_non_super_admin_cannot_reopen_completed_return(): void
    {
        [$user, $rental, , $return] = $this->returnedRental('branch-manager');

        $this->actingAs($user)->post(
            route('rentals.operational-corrections.store', $rental),
            [
                'rental_return_id' => $return->id,
                'reason' => 'Mencoba membuka return tanpa hak Super Admin.',
            ],
        )->assertForbidden();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertDatabaseCount('rental_operational_corrections', 0);
    }

    /** @return array{User, Rental, Asset, RentalReturn} */
    private function returnedRental(string $roleSlug = 'super-admin'): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
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
            'customer_number' => 'PNG-CUS-OP-CORRECTION',
            'name' => 'Pelanggan Koreksi Operasional',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-OP-CORRECTION',
            'name' => 'Kamera Koreksi Operasional',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $plan->id,
            'amount' => 50000,
            'deposit_amount' => 0,
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'CAM-OP-CORRECTION-001',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'items' => [[
                'type' => 'product',
                'id' => $product->id,
                'quantity' => 1,
            ]],
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();

        $rental = Rental::query()->with('items.assets')->firstOrFail();
        $unit = $rental->items->first()->assets->first();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'payment_amount' => 50000,
            'payment_method_id' => $method->id,
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'damaged',
                'notes' => 'Kerusakan awal yang perlu dikoreksi.',
            ]],
        ])->assertSessionHasNoErrors();

        return [
            $user,
            $rental->fresh(['items.assets']),
            $asset->fresh(),
            RentalReturn::query()->firstOrFail(),
        ];
    }
}
