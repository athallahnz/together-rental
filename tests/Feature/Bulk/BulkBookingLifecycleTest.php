<?php

namespace Tests\Feature\Bulk;

use App\Domain\Bookings\BookingManager;
use App\Domain\Inventory\PooledStockManager;
use App\Domain\Rentals\RentalExtensionManager;
use App\Domain\Rentals\RentalManager;
use App\Domain\Rentals\RentalOperationalCorrectionManager;
use App\Domain\Rentals\RentalReturnManager;
use App\Domain\Transfers\TransferEligibilityService;
use App\Domain\Transfers\TransferInventorySynchronizer;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchTransfer;
use App\Models\PackageRate;
use App\Models\Product;
use App\Models\Rental;
use App\Models\RentalPackage;
use App\Models\RentalReturn;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BulkBookingLifecycleTest extends TestCase
{
    use InteractsWithBulkStock;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00'));
        $this->prepareBulkFixture();
    }

    public function test_uat_pool_027_has_three_available_units_without_any_asset(): void
    {
        $availability = app(BookingManager::class)->availability(
            $this->branch->id, $this->product->id, $this->bulkPayload()['starts_at'], $this->plan->id, 1, 3,
        );
        $this->assertSame(3, $availability['available_count']);
        $this->assertTrue($availability['available']);
        $this->assertDatabaseMissing('assets', ['product_id' => $this->product->id]);
        $this->actingAs($this->operator)->post(route('bookings.store'), $this->bulkPayload(3))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_reservations', 1);
        $this->assertDatabaseCount('asset_reservations', 0);
        $this->assertSame(3, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_overbooking_rolls_back_the_entire_booking(): void
    {
        $this->book(2);
        $this->assertValidation(fn () => $this->book(2));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('bulk_reservations', 1);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
        $this->book(1);
        $this->assertSame(3, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_adjacent_bookings_and_a_spanning_booking_use_peak_not_sum(): void
    {
        $this->book(2, 1);
        $this->book(2, 2);
        $data = $this->bulkPayload(1, 1);
        $data['duration_units'] = 2;
        app(BookingManager::class)->create($data, $this->operator);
        $this->assertSame(3, $this->inventory->fresh()->quantity_reserved);
        $this->assertDatabaseCount('bulk_reservations', 3);
    }

    public function test_draft_edit_replaces_own_demand_and_failed_edit_restores_it(): void
    {
        $booking = $this->book(2);
        $manager = app(BookingManager::class);
        $manager->update($booking, $this->bulkPayload(3), $this->operator);
        $this->assertSame(3, $this->inventory->fresh()->quantity_reserved);
        $this->assertValidation(fn () => $manager->update($booking, $this->bulkPayload(4), $this->operator));
        $this->assertSame(3, (int) $booking->bulkReservations()->sum('quantity'));
        $this->assertSame(3, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_confirm_cancel_and_stale_edit_are_guarded(): void
    {
        $booking = $this->book(3);
        $manager = app(BookingManager::class);
        $manager->confirm($booking, $this->operator);
        $this->assertValidation(fn () => $manager->update($booking, $this->bulkPayload(1), $this->operator));
        $manager->cancel($booking, 'Batal', $this->operator);
        $this->assertSame(0, $this->inventory->fresh()->quantity_reserved);
        $this->assertSame('released', $booking->bulkReservations()->firstOrFail()->status);
        $this->assertValidation(fn () => $manager->cancel($booking, 'Ulang', $this->operator));
        $this->assertSame(0, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_confirm_rechecks_stock_and_complete_reservation(): void
    {
        $booking = $this->book(3);
        $this->inventory->update(['quantity_maintenance' => 1]);
        $this->assertValidation(fn () => app(BookingManager::class)->confirm($booking, $this->operator));
        $this->inventory->update(['quantity_maintenance' => 0]);
        $booking->bulkReservations()->update(['quantity' => 2]);
        $this->assertValidation(fn () => app(BookingManager::class)->confirm($booking, $this->operator));
        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_duplicate_product_lines_cannot_bypass_capacity(): void
    {
        $data = $this->bulkPayload(2);
        $data['items'][] = $data['items'][0];
        $this->assertValidation(fn () => app(BookingManager::class)->create($data, $this->operator));
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_branch_isolation_and_quantity_alias(): void
    {
        $other = Branch::query()->where('code', 'PNG')->firstOrFail();
        $result = app(BookingManager::class)->availability($other->id, $this->product->id, $this->bulkPayload()['starts_at'], $this->plan->id, 1, 1);
        $this->assertSame(0, $result['available_count']);
        $this->product->update(['tracking_type' => 'quantity']);
        $this->book(3);
        $this->assertDatabaseCount('bulk_reservations', 1);
    }

    public function test_unmanaged_counters_are_not_erased_or_double_counted(): void
    {
        $this->inventory->update(['quantity_on_hand' => 10, 'quantity_reserved' => 2, 'quantity_rented' => 1, 'quantity_maintenance' => 1, 'quantity_in_transfer' => 1]);
        $booking = $this->book(5);
        $this->assertSame(7, $this->inventory->fresh()->quantity_reserved);
        $this->assertValidation(fn () => $this->book(1));
        app(BookingManager::class)->cancel($booking, 'Batal', $this->operator);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
        $this->assertSame(1, $this->inventory->fresh()->quantity_rented);
    }

    public function test_checkout_converts_quantity_without_fake_assets_and_cannot_repeat(): void
    {
        $booking = $this->book(2);
        $rental = $this->checkoutBulk($booking);
        $this->assertTrue($rental->items->first()->is_bulk);
        $this->assertSame(2, $rental->items->first()->quantity);
        $this->assertSame(0, $this->inventory->fresh()->quantity_reserved);
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
        $this->assertDatabaseCount('rental_item_assets', 0);
        $this->assertDatabaseCount('asset_inspections', 0);
        $this->assertDatabaseMissing('assets', ['product_id' => $this->product->id]);
        $this->assertValidation(fn () => app(RentalManager::class)->checkout($booking, [], $this->operator));
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
    }

    public function test_in_store_uses_the_same_bulk_lifecycle(): void
    {
        $rental = app(RentalManager::class)->createDirect($this->bulkPayload(2, 0), $this->operator);
        $this->assertSame('active', $rental->status);
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
        $this->assertSame(0, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_physical_checkout_is_blocked_until_the_previous_rental_returns(): void
    {
        $first = $this->checkoutBulk($this->book(3, 0));
        $future = $this->book(3, 1);
        app(BookingManager::class)->confirm($future, $this->operator);
        $this->assertValidation(fn () => app(RentalManager::class)->checkout($future, ['checked_out_at' => now()], $this->operator));
        $this->assertSame(3, $this->inventory->fresh()->quantity_rented);
        $this->returnBulk($first, [['quantity' => 3, 'condition' => 'good']]);
        $this->assertSame('active', app(RentalManager::class)->checkout($future->fresh(), ['checked_out_at' => now()], $this->operator)->status);
    }

    public function test_overdue_rental_blocks_future_availability(): void
    {
        $this->checkoutBulk($this->book(3, 0));
        $this->travel(2)->days();
        $this->assertValidation(fn () => $this->book(1));
    }

    public function test_partial_return_can_extend_only_remaining_bulk_quantity(): void
    {
        $rental = $this->checkoutBulk($this->book(3, 0));
        $this->returnBulk($rental, [['quantity' => 1, 'condition' => 'good']]);
        $this->assertSame('partial_return', $rental->fresh()->status);
        $item = $rental->items()->firstOrFail();
        $manager = app(RentalExtensionManager::class);
        $this->assertSame(2, $manager->quoteOptions($rental->fresh())[0]['out_quantity']);
        $extension = $manager->extend($rental->fresh(), ['item_ids' => [$item->id], 'duration_units' => 1], $this->operator);
        $this->assertSame(2, $extension->items->first()->quantity);
        $this->assertSame('20000.00', $extension->total_amount);
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
    }

    public function test_extension_conflict_rolls_back_due_date_and_charges(): void
    {
        $rental = $this->checkoutBulk($this->book(3, 0));
        $this->book(3, 1);
        $item = $rental->items()->firstOrFail();
        $originalDue = $item->due_at->toDateTimeString();
        $this->assertValidation(fn () => app(RentalExtensionManager::class)->extend($rental, ['item_ids' => [$item->id], 'duration_units' => 1], $this->operator));
        $this->assertSame($originalDue, $item->fresh()->due_at->toDateTimeString());
        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_multiple_lines_of_same_product_cannot_overbook_an_extension(): void
    {
        $data = $this->bulkPayload(1, 0);
        $data['items'][] = $data['items'][0];
        $rental = $this->checkoutBulk(app(BookingManager::class)->create($data, $this->operator));
        $this->book(2, 1);
        $due = $rental->due_at->toDateTimeString();
        $this->assertValidation(fn () => app(RentalExtensionManager::class)->extend($rental, ['item_ids' => $rental->items->pluck('id')->all(), 'duration_units' => 1], $this->operator));
        $this->assertSame($due, $rental->fresh()->due_at->toDateTimeString());
        $this->assertDatabaseCount('rental_extensions', 0);
    }

    public function test_partial_good_damaged_and_lost_return_keeps_counters_and_history(): void
    {
        $rental = $this->checkoutBulk($this->book(3, 0));
        $this->returnBulk($rental, [['quantity' => 1, 'condition' => 'good']]);
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
        $this->returnBulk($rental->fresh(), [['quantity' => 1, 'condition' => 'damaged'], ['quantity' => 1, 'condition' => 'lost']]);
        $stock = $this->inventory->fresh();
        $this->assertSame(0, $stock->quantity_rented);
        $this->assertSame(1, $stock->quantity_maintenance);
        $this->assertSame(2, $stock->quantity_on_hand);
        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame(3, $rental->items()->firstOrFail()->returned_quantity);
        $this->assertSame(3, (int) DB::table('rental_return_items')->whereNull('asset_id')->sum('quantity'));
    }

    public function test_overreturn_replay_and_foreign_rental_item_do_not_change_stock(): void
    {
        $rental = $this->checkoutBulk($this->book(2, 0));
        $this->assertValidation(fn () => $this->returnBulk($rental, [['quantity' => 2, 'condition' => 'good'], ['quantity' => 1, 'condition' => 'good']]));
        $this->assertSame(2, $this->inventory->fresh()->quantity_rented);
        $this->actingAs($this->operator)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->toDateTimeString(), 'bulk_items' => [['rental_item_id' => 999999, 'quantity' => 1, 'condition' => 'good']],
        ])->assertSessionHasErrors('bulk_items');
        $this->returnBulk($rental, [['quantity' => 2, 'condition' => 'good']]);
        $this->actingAs($this->operator)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->toDateTimeString(), 'bulk_items' => [['rental_item_id' => $rental->items->first()->id, 'quantity' => 1, 'condition' => 'good']],
        ])->assertStatus(409);
        $this->assertSame(0, $this->inventory->fresh()->quantity_rented);
    }

    public function test_bulk_return_http_validation_and_operational_reopen_guard(): void
    {
        $rental = $this->checkoutBulk($this->book(1, 0));
        $this->actingAs($this->operator)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->toDateTimeString(), 'items' => [],
            'bulk_items' => [['rental_item_id' => $rental->items->first()->id, 'quantity' => 1, 'condition' => 'good']],
        ])->assertSessionHasNoErrors();
        $return = $rental->returns()->firstOrFail();
        $this->assertValidation(fn () => app(RentalOperationalCorrectionManager::class)->reopen($rental->fresh(), [
            'rental_return_id' => $return->id, 'reason' => 'Test guard',
        ], $this->operator));
        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame(0, $this->inventory->fresh()->quantity_rented);
    }

    public function test_mixed_package_uses_snapshot_and_returns_both_tracking_types(): void
    {
        $serialized = Product::query()->create([
            'company_id' => $this->branch->company_id, 'sku' => 'BULK-REG-SERIAL', 'name' => 'Camera',
            'tracking_type' => 'serialized', 'is_rentable' => true, 'is_active' => true,
        ]);
        Asset::query()->create([
            'product_id' => $serialized->id, 'owning_branch_id' => $this->branch->id, 'current_branch_id' => $this->branch->id,
            'asset_code' => 'BULK-REG-ASSET', 'status' => 'available', 'condition' => 'good', 'is_active' => true,
        ]);
        $package = RentalPackage::query()->create([
            'company_id' => $this->branch->company_id, 'branch_id' => $this->branch->id, 'code' => 'MIXED-BULK', 'name' => 'Mixed Package', 'is_active' => true,
        ]);
        $package->items()->create(['product_id' => $this->product->id, 'quantity' => 2, 'is_optional' => false]);
        $package->items()->create(['product_id' => $serialized->id, 'quantity' => 1, 'is_optional' => false]);
        PackageRate::query()->create([
            'package_id' => $package->id, 'branch_id' => $this->branch->id, 'rate_plan_id' => $this->plan->id, 'amount' => 10000, 'is_active' => true,
        ]);
        $data = $this->bulkPayload(1, 0);
        $data['items'] = [['type' => 'package', 'id' => $package->id, 'quantity' => 1]];
        $booking = app(BookingManager::class)->create($data, $this->operator);
        $package->items()->update(['quantity' => 10]);
        $rental = $this->checkoutBulk($booking);
        $this->assertSame(3, $rental->items->sum('quantity'));
        $this->assertEquals(10000, $rental->items->sum('total_amount'));
        $this->assertDatabaseCount('rental_item_assets', 1);
        $bulkItem = $rental->items->firstWhere('is_bulk', true);
        $serialItem = $rental->items->firstWhere('is_bulk', false);
        app(RentalReturnManager::class)->process($rental, [
            'returned_at' => now()->toDateTimeString(),
            'items' => [['rental_item_asset_id' => $serialItem->assets->first()->id, 'condition' => 'good']],
            'bulk_items' => [['rental_item_id' => $bulkItem->id, 'quantity' => 2, 'condition' => 'good']],
        ], $this->operator);
        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame(0, $this->inventory->fresh()->quantity_rented);
        $this->assertSame('available', $serialItem->assets->first()->asset->fresh()->status);
    }

    public function test_controller_payload_exposes_bulk_reservations_and_return_quantity(): void
    {
        $booking = $this->book(2);
        $this->actingAs($this->operator)->get(route('bookings.show', $booking))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('booking.items.0.bulk_reservations', 1)->where('booking.items.0.bulk_reservations.0.quantity', 2));
        app(BookingManager::class)->confirm($booking, $this->operator);
        $this->actingAs($this->operator)->get(route('rentals.checkout.create', $booking))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('booking.items.0.bulk_reservations', 1));
    }

    public function test_transfer_protects_future_bookings_and_hold_release_is_consistent(): void
    {
        $this->book(2, 10);
        $transfer = $this->transfer(2);
        $this->assertNotEmpty(app(TransferEligibilityService::class)->blockers($transfer));
        $this->assertValidation(fn () => DB::transaction(fn () => app(TransferInventorySynchronizer::class)->hold($transfer)));
        $this->assertSame(0, $this->inventory->fresh()->quantity_in_transfer);
        $transfer->items()->update(['quantity' => 1]);
        $transfer->unsetRelation('items');
        DB::transaction(fn () => app(TransferInventorySynchronizer::class)->hold($transfer));
        $this->assertSame(1, $this->inventory->fresh()->quantity_in_transfer);
        $this->assertValidation(fn () => $this->book(2, 10));
        DB::transaction(fn () => app(TransferInventorySynchronizer::class)->release($transfer));
        $this->assertSame(0, $this->inventory->fresh()->quantity_in_transfer);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
    }

    public function test_transfer_receipt_moves_stock_once_without_moving_booking_demand(): void
    {
        $this->book(2, 10);
        $transfer = $this->transfer(1);
        $sync = app(TransferInventorySynchronizer::class);
        DB::transaction(fn () => $sync->hold($transfer));
        $item = $transfer->items()->firstOrFail();
        DB::transaction(function () use ($sync, $transfer, $item): void {
            $sync->receivePooled($transfer, $item, 1);
            $item->update(['received_quantity' => 1]);
        });
        $this->assertSame(2, $this->inventory->fresh()->quantity_on_hand);
        $this->assertSame(0, $this->inventory->fresh()->quantity_in_transfer);
        $this->assertSame(2, $this->inventory->fresh()->quantity_reserved);
        $this->assertSame(1, BranchInventory::query()->where('branch_id', $transfer->to_branch_id)->where('product_id', $this->product->id)->firstOrFail()->quantity_on_hand);
        $this->assertValidation(fn () => DB::transaction(fn () => $sync->receivePooled($transfer, $item->fresh(), 1)));
    }

    public function test_public_and_internal_availability_agree_for_drafts_and_nonoverlap(): void
    {
        DB::table('branch_settings')->updateOrInsert(
            ['branch_id' => $this->branch->id, 'key' => 'public_catalog_enabled'],
            ['value' => 'true', 'value_type' => 'boolean', 'is_public' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $this->book(2, 1);
        $this->book(2, 2);
        $this->getJson('/rental/availability?'.http_build_query([
            'branch' => 'MDN', 'type' => 'product', 'slug' => $this->product->slug,
            'starts_at' => '2026-10-02T08:00', 'ends_at' => '2026-10-04T08:00', 'quantity' => 1,
        ]))->assertOk()->assertJsonPath('data.availability.available_units', 1)
            ->assertJsonPath('data.availability.reserved_units', 2);
        $this->assertSame(1, app(PooledStockManager::class)->available(
            $this->branch->id, $this->product->id, CarbonImmutable::parse('2026-10-02 08:00'), CarbonImmutable::parse('2026-10-04 08:00'),
        ));
    }

    public function test_availability_can_ignore_only_an_accessible_draft_booking(): void
    {
        $booking = $this->book(3);
        $this->actingAs($this->operator)->getJson(route('bookings.availability', [
            'branch_id' => $this->branch->id, 'product_id' => $this->product->id,
            'starts_at' => $this->bulkPayload()['starts_at'], 'rate_plan_id' => $this->plan->id,
            'duration_units' => 1, 'quantity' => 3, 'ignore_booking_id' => $booking->id,
        ]))->assertOk()->assertJsonPath('available_count', 3);
        app(BookingManager::class)->confirm($booking, $this->operator);
        $this->actingAs($this->operator)->getJson(route('bookings.availability', [
            'branch_id' => $this->branch->id, 'product_id' => $this->product->id,
            'starts_at' => $this->bulkPayload()['starts_at'], 'rate_plan_id' => $this->plan->id,
            'duration_units' => 1, 'quantity' => 3, 'ignore_booking_id' => $booking->id,
        ]))->assertUnprocessable();
    }

    private function returnBulk(Rental $rental, array $lines): RentalReturn
    {
        $item = $rental->items()->where('is_bulk', true)->firstOrFail();

        return app(RentalReturnManager::class)->process($rental, [
            'returned_at' => now()->toDateTimeString(),
            'bulk_items' => array_map(fn (array $line): array => ['rental_item_id' => $item->id, ...$line], $lines),
        ], $this->operator);
    }

    private function transfer(int $quantity): BranchTransfer
    {
        $destination = Branch::query()->where('code', 'PNG')->firstOrFail();
        $transfer = BranchTransfer::query()->create([
            'company_id' => $this->branch->company_id, 'from_branch_id' => $this->branch->id, 'to_branch_id' => $destination->id,
            'transfer_number' => 'BULK-TRANSFER-TEST', 'status' => 'draft', 'planned_dispatch_at' => now(),
        ]);
        $transfer->items()->create(['product_id' => $this->product->id, 'quantity' => $quantity, 'line_number' => 1, 'status' => 'pending']);

        return $transfer;
    }
}
