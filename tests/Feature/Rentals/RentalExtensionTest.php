<?php

namespace Tests\Feature\Rentals;

use App\Domain\Catalog\AssetScheduleService;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MaintenanceOrder;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class RentalExtensionTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_active_rental_extension_updates_financials_schedule_payment_and_audit(): void
    {
        [$user, $rental] = $this->activeRental();
        $item = $rental->items()->with('assets.asset')->firstOrFail();
        $oldDueAt = CarbonImmutable::parse((string) $item->due_at);
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $rental->branch);

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'payment_amount' => 100000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'notes' => 'Customer memperpanjang satu hari.',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('rentals.show', $rental));

        $extension = $rental->extensions()->with('items')->firstOrFail();
        $rental->refresh();
        $item->refresh();

        $this->assertSame('approved', $extension->status);
        $this->assertTrue($oldDueAt->addDay()->equalTo($item->due_at));
        $this->assertTrue($oldDueAt->addDay()->equalTo($rental->due_at));
        $this->assertSame('100000.00', $extension->total_amount);
        $this->assertSame('100000.00', $extension->paid_amount);
        $this->assertSame('200000.00', $rental->total_amount);
        $this->assertSame('100000.00', $rental->paid_amount);
        $this->assertSame('100000.00', $rental->balance_due);
        $this->assertDatabaseHas('rental_extension_items', [
            'rental_extension_id' => $extension->id,
            'rental_item_id' => $item->id,
            'quantity' => 1,
            'total_amount' => 100000,
        ]);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'rental_extension_id' => $extension->id,
            'source_context' => 'rental_extension',
            'status' => 'completed',
            'amount' => 100000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.extended',
        ]);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_partial_item_extension_only_changes_selected_item_and_calendar(): void
    {
        [$user, $rental, $assets] = $this->activeRental(twoItems: true);
        $items = $rental->items()->orderBy('id')->get();
        $selected = $items[0];
        $untouched = $items[1];
        $oldSelectedDue = CarbonImmutable::parse((string) $selected->due_at);
        $oldUntouchedDue = CarbonImmutable::parse((string) $untouched->due_at);

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$selected->id],
            'payment_amount' => 0,
        ])->assertSessionHasNoErrors();

        $selected->refresh();
        $untouched->refresh();
        $rental->refresh();

        $this->assertTrue($oldSelectedDue->addDay()->equalTo($selected->due_at));
        $this->assertTrue($oldUntouchedDue->equalTo($untouched->due_at));
        $this->assertTrue($oldUntouchedDue->equalTo($rental->due_at));

        $calendar = app(AssetScheduleService::class);
        $rangeEnd = $oldSelectedDue->addDays(2);
        $selectedEvent = collect($calendar->calendar(
            $assets[0],
            $oldSelectedDue->subHour(),
            $rangeEnd,
            false,
        )['events'])->firstWhere('type', 'rental');
        $untouchedEvent = collect($calendar->calendar(
            $assets[1],
            $oldUntouchedDue->subHour(),
            $rangeEnd,
            false,
        )['events'])->firstWhere('type', 'rental');

        $this->assertNotNull($selectedEvent);
        $this->assertNotNull($untouchedEvent);
        $this->assertTrue(
            $oldSelectedDue->addDay()->equalTo(CarbonImmutable::parse($selectedEvent['ends_at'])),
        );
        $this->assertTrue(
            $oldUntouchedDue->equalTo(CarbonImmutable::parse($untouchedEvent['ends_at'])),
        );

        $untouchedUnit = $untouched->assets()->firstOrFail();
        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $untouchedUnit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partial_return', $rental->fresh()->status);
        $this->assertTrue(
            $oldSelectedDue->addDay()->equalTo($rental->fresh()->due_at),
        );
    }

    public function test_extension_is_rejected_atomically_when_asset_schedule_conflicts(): void
    {
        [$user, $rental, $assets] = $this->activeRental();
        $item = $rental->items()->firstOrFail();
        $oldDueAt = CarbonImmutable::parse((string) $item->due_at);
        $oldTotal = (string) $rental->total_amount;
        $oldBalance = (string) $rental->balance_due;

        MaintenanceOrder::query()->create([
            'branch_id' => $rental->branch_id,
            'asset_id' => $assets[0]->id,
            'maintenance_number' => 'MNT-PNG-CONFLICT-0001',
            'type' => 'inspection',
            'status' => 'in_progress',
            'problem_description' => 'Jadwal pemeriksaan setelah rental semula selesai.',
            'reported_at' => $oldDueAt->addHour(),
            'started_at' => $oldDueAt->addHour(),
            'completed_at' => $oldDueAt->addHours(3),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'payment_amount' => 0,
        ])->assertSessionHasErrors('item_ids');

        $this->assertDatabaseCount('rental_extensions', 0);
        $this->assertTrue($oldDueAt->equalTo($item->fresh()->due_at));
        $this->assertSame($oldTotal, $rental->fresh()->total_amount);
        $this->assertSame($oldBalance, $rental->fresh()->balance_due);
    }

    public function test_partially_returned_line_is_not_eligible_for_extension(): void
    {
        [$user, $rental] = $this->activeRental(quantity: 2);
        $item = $rental->items()->with('assets')->firstOrFail();
        $unit = $item->assets->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partial_return', $item->fresh()->status);
        $this->assertSame('partial_return', $rental->fresh()->status);

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'payment_amount' => 0,
        ])->assertSessionHasErrors('item_ids');

        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_member_discount_is_applied_to_extension_by_the_same_pricing_engine(): void
    {
        [$user, $rental] = $this->activeRental();
        $rental->customer()->update([
            'is_member' => true,
            'member_number' => 'MBR-EXTENSION',
            'member_since' => now()->subMonth()->toDateString(),
        ]);
        $item = $rental->items()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'payment_amount' => 0,
        ])->assertSessionHasNoErrors();

        $extension = $rental->extensions()->firstOrFail();
        $this->assertSame('100000.00', $extension->subtotal);
        $this->assertSame('10000.00', $extension->discount_amount);
        $this->assertSame('90000.00', $extension->total_amount);
        $this->assertSame('membership', $extension->pricing_snapshot['discount_strategy']);
        $this->assertSame('190000.00', $rental->fresh()->total_amount);
        $this->assertSame('10000.00', $rental->fresh()->discount_amount);
    }

    public function test_promotion_code_is_applied_to_extension_and_snapshotted(): void
    {
        [$user, $rental] = $this->activeRental();
        $promotion = Promotion::query()->create([
            'company_id' => $rental->branch->company_id,
            'branch_id' => $rental->branch_id,
            'code' => 'EXT15K',
            'name' => 'Extension 15K',
            'type' => 'fixed',
            'value' => 15000,
            'minimum_transaction' => 0,
            'bonus_duration' => 0,
            'is_active' => true,
            'rules' => [],
        ]);
        $item = $rental->items()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'promotion_code' => $promotion->code,
            'payment_amount' => 0,
        ])->assertSessionHasNoErrors();

        $extension = $rental->extensions()->firstOrFail();
        $this->assertSame($promotion->id, $extension->promotion_id);
        $this->assertSame('15000.00', $extension->discount_amount);
        $this->assertSame('85000.00', $extension->total_amount);
        $this->assertSame('EXT15K', $extension->pricing_snapshot['promotion']['code']);
        $this->assertSame('promotion', $extension->pricing_snapshot['discount_strategy']);
    }

    public function test_voiding_extension_payment_reopens_rental_balance_without_cancelling_extension(): void
    {
        [$user, $rental] = $this->activeRental();
        $item = $rental->items()->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $rental->branch);

        $this->actingAs($user)->post(route('rentals.extensions.store', $rental), [
            'duration_units' => 1,
            'item_ids' => [$item->id],
            'payment_amount' => 100000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
        ])->assertSessionHasNoErrors();

        $extension = $rental->extensions()->firstOrFail();
        $payment = Payment::query()
            ->where('rental_extension_id', $extension->id)
            ->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.void', $payment), [
            'reason' => 'Salah pencatatan pembayaran extension.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('void', $payment->fresh()->status);
        $this->assertSame('approved', $extension->fresh()->status);
        $this->assertSame('0.00', $extension->fresh()->paid_amount);
        $this->assertSame('0.00', $rental->fresh()->paid_amount);
        $this->assertSame('200000.00', $rental->fresh()->balance_due);
        $this->assertDatabaseCount('cash_transactions', 2);
    }

    /**
     * @return array{0: User, 1: Rental, 2: list<Asset>}
     */
    private function activeRental(
        int $quantity = 1,
        bool $twoItems = false,
    ): array {
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
            'customer_number' => 'PNG-CUS-EXTENSION',
            'name' => 'Pelanggan Extension',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();

        $products = [];
        $assets = [];
        $itemCount = $twoItems ? 2 : 1;

        for ($productIndex = 1; $productIndex <= $itemCount; $productIndex++) {
            $product = Product::query()->create([
                'company_id' => $branch->company_id,
                'sku' => "EXT-PRODUCT-{$productIndex}",
                'name' => "Produk Extension {$productIndex}",
                'tracking_type' => 'serialized',
                'is_rentable' => true,
                'is_active' => true,
            ]);
            ProductRate::query()->create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'rate_plan_id' => $plan->id,
                'amount' => 100000,
                'deposit_amount' => 0,
                'is_active' => true,
            ]);
            $products[] = $product;

            $assetQuantity = $twoItems ? 1 : $quantity;
            for ($assetIndex = 1; $assetIndex <= $assetQuantity; $assetIndex++) {
                $assets[] = Asset::query()->create([
                    'product_id' => $product->id,
                    'owning_branch_id' => $branch->id,
                    'current_branch_id' => $branch->id,
                    'asset_code' => "EXT-{$productIndex}-{$assetIndex}",
                    'status' => 'available',
                    'condition' => 'good',
                    'is_active' => true,
                ]);
            }
        }

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'items' => collect($products)->map(fn (Product $product): array => [
                'type' => 'product',
                'id' => $product->id,
                'quantity' => $twoItems ? 1 : $quantity,
            ])->all(),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors()
            ->assertRedirect();

        return [
            $user,
            Rental::query()->with(['branch', 'items.assets.asset'])->firstOrFail(),
            $assets,
        ];
    }
}
