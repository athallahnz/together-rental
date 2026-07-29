<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_return_updates_units_financials_and_rental_atomically(): void
    {
        [$user, $rental, $asset] = $this->activeRental();
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'discount_amount' => 5000,
            'payment_amount' => 80000,
            'payment_method_id' => $method->id,
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'damaged',
                'late_fee_amount' => 10000,
                'damage_fee_amount' => 20000,
                'cleaning_fee_amount' => 5000,
                'notes' => 'Grip terkelupas dan perlu pemeriksaan.',
            ]],
        ])->assertSessionHasNoErrors();

        $rental->refresh();
        $this->assertSame('returned', $rental->status);
        $this->assertSame('maintenance', $asset->fresh()->status);
        $this->assertSame('damaged', $asset->fresh()->condition);
        $this->assertSame('0.00', $rental->balance_due);
        $this->assertDatabaseHas('rental_returns', [
            'rental_id' => $rental->id,
            'type' => 'final',
            'total_charge_amount' => 30000,
        ]);
        $this->assertDatabaseHas('asset_inspections', [
            'asset_id' => $asset->id,
            'type' => 'return',
            'condition' => 'damaged',
        ]);
        $this->assertDatabaseHas('damage_charges', [
            'asset_id' => $asset->id,
            'amount' => 20000,
            'status' => 'charged',
        ]);
        $this->assertDatabaseHas('maintenance_orders', [
            'branch_id' => $rental->branch_id,
            'asset_id' => $asset->id,
            'type' => 'repair',
            'status' => 'reported',
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $rental->branch_id,
            'product_id' => $asset->product_id,
            'quantity_maintenance' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.return_completed',
        ]);
    }

    public function test_partial_return_keeps_rental_open_until_last_unit_returns(): void
    {
        [$user, $rental] = $this->activeRental(2);
        $units = $rental->items()->firstOrFail()->assets()->get();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $units[0]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partial_return', $rental->fresh()->status);
        $this->assertNull($rental->fresh()->returned_at);
        $this->assertSame('available', $units[0]->asset->fresh()->status);
        $this->assertSame('rented', $units[1]->asset->fresh()->status);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'payment_amount' => 100000,
            'payment_method_id' => $method->id,
            'items' => [[
                'rental_item_asset_id' => $units[1]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertNotNull($rental->fresh()->returned_at);
        $this->assertDatabaseCount('rental_returns', 2);
    }

    public function test_return_rejects_foreign_or_already_returned_unit(): void
    {
        [$user, $rental] = $this->activeRental();
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();
        $payload = [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'payment_amount' => 50000,
            'payment_method_id' => PaymentMethod::query()
                ->where('code', 'CASH')
                ->firstOrFail()
                ->id,
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ];

        $this->actingAs($user)->post(route('rentals.return.store', $rental), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('rentals.return.store', $rental), $payload)
            ->assertStatus(409);
        $this->assertDatabaseCount('rental_returns', 1);
    }

    public function test_final_return_is_rejected_atomically_until_fully_paid(): void
    {
        [$user, $rental, $asset] = $this->activeRental();
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
                'late_fee_amount' => 10000,
            ]],
        ])->assertSessionHasErrors([
            'payment_amount' => 'Rental belum lunas. Sisa tagihan Rp60.000 harus dibayar sebelum pengembalian diselesaikan.',
        ]);

        $this->assertSame('active', $rental->fresh()->status);
        $this->assertSame('50000.00', $rental->fresh()->balance_due);
        $this->assertSame('rented', $asset->fresh()->status);
        $this->assertSame('out', $unit->fresh()->status);
        $this->assertDatabaseCount('rental_returns', 0);
        $this->assertDatabaseCount('rental_return_items', 0);
    }

    /** @return array{User, Rental, Asset} */
    private function activeRental(int $quantity = 1): array
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
            Role::query()->where('slug', 'super-admin')->firstOrFail()->id,
            ['branch_id' => null, 'assigned_at' => now()],
        );
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-RETURN',
            'name' => 'Pelanggan Pengembalian',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-RETURN',
            'name' => 'Kamera Return',
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
        $assets = collect();

        for ($index = 1; $index <= $quantity; $index++) {
            $assets->push(Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => "CAM-RETURN-00{$index}",
                'status' => 'available',
                'condition' => 'good',
                'is_active' => true,
            ]));
        }

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
                'quantity' => $quantity,
            ]],
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();

        return [$user, Rental::query()->with('items.assets.asset')->firstOrFail(), $assets[0]];
    }
}
