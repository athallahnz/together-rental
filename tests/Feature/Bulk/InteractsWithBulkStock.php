<?php

namespace Tests\Feature\Bulk;

use App\Domain\Bookings\BookingManager;
use App\Domain\Rentals\RentalManager;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Validation\ValidationException;

trait InteractsWithBulkStock
{
    private User $operator;

    private Branch $branch;

    private Customer $customer;

    private RatePlan $plan;

    private Product $product;

    private BranchInventory $inventory;

    private function prepareBulkFixture(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $base = Branch::query()->where('code', 'PNG')->firstOrFail();
        $this->branch = Branch::query()->firstOrCreate(['company_id' => $base->company_id, 'code' => 'MDN'], [
            'name' => 'Together Kamera Madiun', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
        ]);
        $this->operator = User::factory()->create([
            'company_id' => $this->branch->company_id, 'current_branch_id' => $this->branch->id,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        $this->operator->branches()->attach($this->branch->id, ['is_default' => true, 'is_active' => true]);
        $this->operator->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail()->id, [
            'branch_id' => null, 'assigned_at' => now(),
        ]);
        $this->customer = Customer::query()->create([
            'company_id' => $this->branch->company_id, 'registered_branch_id' => $this->branch->id,
            'customer_number' => 'MDN-BULK-TEST', 'name' => 'Pelanggan Bulk', 'status' => 'active', 'risk_level' => 'normal',
        ]);
        $this->plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $this->product = Product::query()->create([
            'company_id' => $this->branch->company_id, 'sku' => 'UAT-POOL-027', 'name' => 'Pooled Test',
            'tracking_type' => 'bulk', 'is_rentable' => true, 'is_active' => true,
            'is_public' => true, 'slug' => 'pooled-test',
        ]);
        ProductRate::query()->create([
            'product_id' => $this->product->id, 'branch_id' => $this->branch->id, 'rate_plan_id' => $this->plan->id,
            'amount' => 10000, 'deposit_amount' => 0, 'is_active' => true,
        ]);
        $this->inventory = BranchInventory::query()->create([
            'branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity_on_hand' => 3,
            'quantity_reserved' => 0, 'quantity_rented' => 0, 'quantity_maintenance' => 0, 'quantity_in_transfer' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function bulkPayload(int $quantity = 1, int $days = 1): array
    {
        return [
            'branch_id' => $this->branch->id, 'customer_id' => $this->customer->id, 'rate_plan_id' => $this->plan->id,
            'starts_at' => now()->addDays($days)->startOfHour()->format('Y-m-d H:i:s'),
            'duration_units' => 1, 'source' => 'counter',
            'items' => [['type' => 'product', 'id' => $this->product->id, 'quantity' => $quantity]],
        ];
    }

    private function book(int $quantity = 1, int $days = 1): Booking
    {
        return app(BookingManager::class)->create($this->bulkPayload($quantity, $days), $this->operator);
    }

    private function checkoutBulk(Booking $booking): Rental
    {
        app(BookingManager::class)->confirm($booking, $this->operator);

        return app(RentalManager::class)->checkout($booking->fresh(), [
            'checked_out_at' => now()->toDateTimeString(),
            'payment_amount' => $booking->total_amount,
            'payment_method_id' => PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail()->id,
            'payment_reference' => 'BULK-TEST',
        ], $this->operator);
    }

    private function assertValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a stock/lifecycle validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
