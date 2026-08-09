<?php

namespace Tests\Feature\Transfers;

use App\Models\AssetReservation;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BranchTransfer;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferEligibilityTest extends TestCase
{
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_future_booking_conflict_blocks_transfer_approval_without_auto_cancelling_booking(): void
    {
        $fixture = $this->transferFixture();
        $customer = Customer::query()->create([
            'company_id' => $fixture['origin']->company_id,
            'registered_branch_id' => $fixture['origin']->id,
            'customer_number' => 'CUS-TRF-CONFLICT',
            'name' => 'Pelanggan Konflik Transfer',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $booking = Booking::query()->create([
            'branch_id' => $fixture['origin']->id,
            'customer_id' => $customer->id,
            'booking_number' => 'BKG-TRF-CONFLICT',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(6),
            'created_by' => $fixture['originManager']->id,
        ]);
        $bookingItem = BookingItem::query()->create([
            'booking_id' => $booking->id,
            'product_id' => $fixture['product']->id,
            'description' => $fixture['product']->name,
            'quantity' => 1,
        ]);
        AssetReservation::query()->create([
            'branch_id' => $fixture['origin']->id,
            'booking_id' => $booking->id,
            'booking_item_id' => $bookingItem->id,
            'asset_id' => $fixture['asset']->id,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'status' => 'reserved',
        ]);

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture))
            ->assertSessionHasNoErrors();
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.submit', $transfer))
            ->assertSessionHasErrors('transfer');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('reserved', AssetReservation::query()->firstOrFail()->status);
        $this->assertSame('available', $fixture['asset']->fresh()->status);
        $this->assertSame('draft', $transfer->fresh()->status->value);
        $this->assertDatabaseCount('branch_transfer_approvals', 0);
    }

    public function test_pooled_inventory_is_held_after_second_approval_and_moved_on_receiving(): void
    {
        $fixture = $this->transferFixture();
        $payload = [
            ...$this->serializedPayload($fixture, true),
            'items' => [[
                'product_id' => $fixture['pooledProduct']->id,
                'asset_id' => null,
                'quantity' => 4,
                'condition_before' => 'good',
                'notes' => 'Empat unit aksesori.',
            ]],
        ];

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $payload)
            ->assertSessionHasNoErrors();
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Stok tujuan siap menerima.',
                'revision_number' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            4,
            $fixture['pooledInventory']->fresh()->quantity_in_transfer,
        );
        $this->assertSame('approved', $transfer->fresh()->status->value);
    }

    public function test_insufficient_pooled_stock_is_reported_by_preflight(): void
    {
        $fixture = $this->transferFixture();
        $payload = [
            ...$this->serializedPayload($fixture),
            'items' => [[
                'product_id' => $fixture['pooledProduct']->id,
                'asset_id' => null,
                'quantity' => 7,
                'condition_before' => 'good',
                'notes' => null,
            ]],
        ];

        $this->actingAs($fixture['originManager'])
            ->postJson(route('transfers.preflight'), $payload)
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonFragment(['code' => 'INSUFFICIENT_STOCK']);

        $this->assertDatabaseCount('branch_transfers', 0);
    }

    public function test_approved_transfer_preflight_ignores_its_own_hold_context(): void
    {
        $fixture = $this->transferFixture();

        $this->actingAs($fixture['originManager'])
            ->post(
                route('transfers.store'),
                $this->serializedPayload($fixture, true),
            )
            ->assertSessionHasNoErrors();
        $transfer = BranchTransfer::query()->firstOrFail();

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima unit.',
                'revision_number' => $transfer->revision_number,
            ])
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame('approved', $transfer->status->value);
        $this->assertSame('in_transit', $fixture['asset']->fresh()->status);

        $this->actingAs($fixture['originManager'])
            ->postJson(route('transfers.preflight'), [
                ...$this->serializedPayload($fixture),
                'context_transfer_id' => $transfer->id,
                'lock_version' => $transfer->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonCount(0, 'blockers');

        $this->assertDatabaseCount('branch_transfers', 1);
    }
}
