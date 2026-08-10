<?php

namespace Tests\Feature\Pricing;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingPromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_discount_is_ten_percent_flat_for_multi_day_booking(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture(member: true);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['duration_units'] = 3;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('450000.00', $booking->subtotal);
        $this->assertSame('45000.00', $booking->discount_amount);
        $this->assertSame('405000.00', $booking->total_amount);
        $this->assertSame('membership', $booking->pricing_snapshot['discount_strategy']);
    }

    public function test_promotion_uses_best_discount_by_default_and_can_explicitly_stack_member(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture(member: true);
        $promo = $this->promotion($branch, [
            'code' => 'PROMO20', 'type' => 'percentage', 'value' => 20,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('30000.00', $booking->discount_amount);
        $this->assertSame('120000.00', $booking->total_amount);
        $this->assertSame('best_discount', $booking->pricing_snapshot['discount_strategy']);

        $stackPromo = $this->promotion($branch, [
            'code' => 'STACK20', 'type' => 'percentage', 'value' => 20,
            'rules' => ['allow_member_stack' => true],
        ]);
        $payload['promotion_code'] = $stackPromo->code;
        $payload['starts_at'] = now()->addDays(3)->setTime(8, 0)->format('Y-m-d H:i:s');
        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $stacked = Booking::query()->latest('id')->firstOrFail();
        $this->assertSame('45000.00', $stacked->discount_amount);
        $this->assertSame('105000.00', $stacked->total_amount);
        $this->assertSame('stacked', $stacked->pricing_snapshot['discount_strategy']);
    }

    public function test_percentage_promotion_respects_maximum_discount_and_minimum_transaction(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $promo = $this->promotion($branch, [
            'code' => 'CAP50', 'type' => 'percentage', 'value' => 50,
            'maximum_discount' => 20000, 'minimum_transaction' => 100000,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('20000.00', $booking->discount_amount);
        $this->assertSame('130000.00', $booking->total_amount);
    }

    public function test_bonus_duration_extends_schedule_without_charging_extra_unit(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $promo = $this->promotion($branch, [
            'code' => 'BONUS1', 'type' => 'bonus_duration', 'bonus_duration' => 1,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('150000.00', $booking->subtotal);
        $this->assertSame('150000.00', $booking->total_amount);
        $this->assertTrue($booking->ends_at->equalTo($booking->starts_at->copy()->addDays(2)));
        $this->assertSame(1, $booking->pricing_snapshot['bonus_duration_units']);
    }

    public function test_promotion_usage_limit_is_enforced_and_cancelled_booking_releases_quota(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $promo = $this->promotion($branch, [
            'code' => 'ONCE', 'type' => 'fixed', 'value' => 10000, 'usage_limit' => 1,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;
        $this->actingAs($user)->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
        $first = Booking::query()->firstOrFail();

        $payload['starts_at'] = now()->addDays(3)->setTime(8, 0)->format('Y-m-d H:i:s');
        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('promotion_code');

        $this->actingAs($user)->post(route('bookings.cancel', $first), [
            'reason' => 'Melepas kuota promo untuk pengujian.',
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
    }

    public function test_pricing_snapshot_is_immutable_when_promotion_master_changes(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $promo = $this->promotion($branch, [
            'code' => 'SNAP15', 'name' => 'Snapshot 15%', 'type' => 'percentage', 'value' => 15,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->assertSame('127500.00', $booking->total_amount);
        $this->assertSame(15.0, (float) $booking->pricing_snapshot['promotion']['value']);
        $this->assertSame('Snapshot 15%', $booking->pricing_snapshot['promotion']['name']);

        $promo->update(['name' => 'Snapshot berubah', 'value' => 50]);
        $booking->refresh();

        $this->assertSame('127500.00', $booking->total_amount);
        $this->assertSame(15.0, (float) $booking->pricing_snapshot['promotion']['value']);
        $this->assertSame('Snapshot 15%', $booking->pricing_snapshot['promotion']['name']);
    }

    public function test_branch_scoped_promotion_cannot_be_used_from_another_branch(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $foreignBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $promo = $this->promotion($foreignBranch, [
            'code' => 'MDNONLY', 'type' => 'fixed', 'value' => 10000,
        ]);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['promotion_code'] = $promo->code;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('promotion_code');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_promotion_master_crud_is_audited_and_archive_preserves_history(): void
    {
        [$user, $branch] = $this->fixture();
        $this->actingAs($user)->post(route('catalog.promotions.store'), [
            'branch_id' => $branch->id,
            'code' => 'WEEKEND10',
            'name' => 'Weekend 10%',
            'type' => 'percentage',
            'value' => 10,
            'minimum_transaction' => 0,
            'bonus_duration' => 0,
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $promo = Promotion::query()->where('code', 'WEEKEND10')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['event' => 'promotion.created', 'subject_id' => $promo->id]);

        $this->actingAs($user)->delete(route('catalog.promotions.archive', $promo))
            ->assertSessionHasNoErrors();
        $this->assertFalse($promo->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['event' => 'promotion.archived', 'subject_id' => $promo->id]);
    }

    /** @return array{User, Branch, Customer, RatePlan, Product} */
    private function fixture(bool $member = false): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail()->id, [
            'branch_id' => null, 'assigned_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-PRICING',
            'name' => 'Pelanggan Pricing',
            'is_member' => $member,
            'member_number' => $member ? 'MBR-PRICING' : null,
            'member_since' => $member ? now()->subMonth()->toDateString() : null,
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-PRICING',
            'name' => 'Kamera Pricing',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $plan->id,
            'amount' => 150000,
            'deposit_amount' => 500000,
            'is_active' => true,
        ]);
        foreach ([1, 2] as $number) {
            Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => "CAM-PRICING-00{$number}",
                'status' => 'available',
                'condition' => 'good',
                'is_active' => true,
            ]);
        }

        return [$user, $branch, $customer, $plan, $product];
    }

    /** @param array<string, mixed> $override */
    private function promotion(Branch $branch, array $override): Promotion
    {
        return Promotion::query()->create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'code' => 'PROMO',
            'name' => 'Promo Test',
            'type' => 'percentage',
            'value' => 10,
            'minimum_transaction' => 0,
            'bonus_duration' => 0,
            'is_active' => true,
            'rules' => [],
            ...$override,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Branch $branch, Customer $customer, RatePlan $plan, Product $product): array
    {
        return [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'items' => [['type' => 'product', 'id' => $product->id, 'quantity' => 1]],
        ];
    }
}
