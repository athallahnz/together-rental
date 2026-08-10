<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class RentalCollateralTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_direct_rental_records_physical_collateral_separately_from_cash_deposit(): void
    {
        Storage::fake('local');
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->rentalPayload($branch, $customer, $plan, $product),
            'payment_amount' => 50000,
            'deposit_paid' => 100000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'collaterals' => [[
                'type' => 'KTP',
                'number' => '3502010101010001',
                'holder_name' => 'Pelanggan Collateral',
                'notes' => 'Disimpan di laci kasir.',
                'document' => UploadedFile::fake()->image('ktp.jpg'),
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $collateral = RentalCollateral::query()->firstOrFail();

        $this->assertSame('100000.00', $rental->deposit_amount);
        $this->assertSame('held', $collateral->status);
        $this->assertSame($rental->id, $collateral->rental_id);
        $this->assertSame($customer->id, $collateral->customer_id);
        $this->assertSame($user->id, $collateral->received_by);
        $this->assertNotNull($collateral->received_at);
        $this->assertNotNull($collateral->document_path);
        Storage::disk('local')->assertExists($collateral->document_path);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_received',
            'subject_id' => $collateral->id,
        ]);
    }

    public function test_confirmed_booking_checkout_can_receive_multiple_collaterals(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $payload = $this->bookingPayload($branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'collaterals' => [
                [
                    'type' => 'SIM',
                    'number' => 'SIM-0001',
                    'holder_name' => $customer->name,
                ],
                [
                    'type' => 'Kartu Mahasiswa',
                    'number' => 'MHS-0001',
                    'holder_name' => $customer->name,
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $this->assertDatabaseCount('rental_collaterals', 2);
        $this->assertSame(2, $rental->collaterals()->where('status', 'held')->count());
        $this->assertDatabaseHas('rental_collaterals', [
            'rental_id' => $rental->id,
            'type' => 'SIM',
            'number' => 'SIM-0001',
            'status' => 'held',
        ]);
    }

    public function test_active_rental_can_receive_private_collateral_document_and_foreign_branch_cannot_read_it(): void
    {
        Storage::fake('local');
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'type' => 'KTP',
            'number' => 'KTP-PRIVATE-01',
            'holder_name' => $customer->name,
            'document' => UploadedFile::fake()->image('jaminan.jpg'),
        ])->assertSessionHasNoErrors();

        $collateral = RentalCollateral::query()->firstOrFail();
        $this->actingAs($user)
            ->get(route('rentals.collaterals.document', [$rental, $collateral]))
            ->assertOk();

        $otherBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $other = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $otherBranch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $other->branches()->attach($otherBranch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $other->roles()->attach(
            Role::query()->where('slug', 'rental-operator')->firstOrFail()->id,
            ['branch_id' => $otherBranch->id, 'assigned_at' => now()],
        );

        $this->actingAs($other)
            ->get(route('rentals.collaterals.document', [$rental, $collateral]))
            ->assertNotFound();
    }

    public function test_final_return_is_atomic_until_all_held_collaterals_are_confirmed_returned(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            quantity: 1,
            payInFull: true,
            collateral: true,
        );
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasErrors('returned_collateral_ids');

        $this->assertSame('active', $rental->fresh()->status);
        $this->assertSame('out', $unit->fresh()->status);
        $this->assertSame('held', $collateral->fresh()->status);
        $this->assertDatabaseCount('rental_returns', 0);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'returned_collateral_ids' => [$collateral->id],
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame('returned', $collateral->fresh()->status);
        $this->assertSame($user->id, $collateral->fresh()->returned_by);
        $this->assertNotNull($collateral->fresh()->returned_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_returned',
            'subject_id' => $collateral->id,
        ]);
    }

    public function test_partial_return_can_keep_collateral_held_until_last_unit_returns(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture(2);
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            quantity: 2,
            payInFull: true,
            collateral: true,
        );
        $units = $rental->items()->firstOrFail()->assets()->get();
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $units[0]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partial_return', $rental->fresh()->status);
        $this->assertSame('held', $collateral->fresh()->status);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'returned_collateral_ids' => [$collateral->id],
            'items' => [[
                'rental_item_asset_id' => $units[1]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame('returned', $collateral->fresh()->status);
    }

    public function test_manual_collateral_return_is_guarded_against_duplicate_return(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            collateral: true,
        );
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)
            ->post(route('rentals.collaterals.return', [$rental, $collateral]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('rentals.collaterals.return', [$rental, $collateral]))
            ->assertStatus(409);

        $this->assertSame('returned', $collateral->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_returned',
            'subject_id' => $collateral->id,
        ]);
    }

    /** @return array{User, Branch, Customer, RatePlan, Product, Collection<int, Asset>} */
    private function fixture(int $assetCount = 1): array
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
            'customer_number' => 'PNG-CUS-COLLATERAL',
            'name' => 'Pelanggan Collateral',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-COLLATERAL',
            'name' => 'Kamera Collateral',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $plan->id,
            'amount' => 50000,
            'deposit_amount' => 100000,
            'is_active' => true,
        ]);
        $assets = collect();
        for ($index = 1; $index <= $assetCount; $index++) {
            $assets->push(Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => sprintf('CAM-COL-%03d', $index),
                'status' => 'available',
                'condition' => 'good',
                'is_active' => true,
            ]));
        }

        return [$user, $branch, $customer, $plan, $product, $assets];
    }

    /** @return array<string, mixed> */
    private function bookingPayload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
    ): array {
        return [
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
        ];
    }

    /** @return array<string, mixed> */
    private function rentalPayload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
    ): array {
        return [
            ...$this->bookingPayload($branch, $customer, $plan, $product, $quantity),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
        ];
    }

    private function createDirectRental(
        User $user,
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
        bool $payInFull = false,
        bool $collateral = false,
    ): Rental {
        $payload = $this->rentalPayload($branch, $customer, $plan, $product, $quantity);

        if ($payInFull) {
            $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
            $session = $this->openCashSession($user, $branch);
            $payload['payment_amount'] = 50000 * $quantity;
            $payload['payment_method_id'] = $method->id;
            $payload['cash_session_id'] = $session->id;
        }

        if ($collateral) {
            $payload['collaterals'] = [[
                'type' => 'KTP',
                'number' => 'KTP-HELD-001',
                'holder_name' => $customer->name,
            ]];
        }

        $this->actingAs($user)->post(route('rentals.direct.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        return Rental::query()
            ->with(['items.assets.asset', 'collaterals'])
            ->latest('id')
            ->firstOrFail();
    }
}
