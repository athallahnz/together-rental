<?php

namespace Tests\Feature\Transfers;

use App\Models\BranchTransfer;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferApprovalTest extends TestCase
{
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_two_side_approval_places_asset_on_realtime_transfer_hold(): void
    {
        $fixture = $this->transferFixture();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true))
            ->assertSessionHasNoErrors();

        $transfer = BranchTransfer::query()->firstOrFail();
        $this->assertSame('pending_approval', $transfer->status->value);
        $this->assertDatabaseHas('branch_transfer_approvals', [
            'branch_transfer_id' => $transfer->id,
            'revision_number' => 1,
            'side' => 'origin',
            'decision' => 'approved',
        ]);
        $this->assertSame('available', $fixture['asset']->fresh()->status);

        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima.',
                'revision_number' => 1,
            ])
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $asset = $fixture['asset']->fresh();

        $this->assertSame('approved', $transfer->status->value);
        $this->assertSame('in_transit', $asset->status);
        $this->assertSame($fixture['origin']->id, $asset->current_branch_id);
        $this->assertSame($fixture['origin']->id, $asset->owning_branch_id);
        $this->assertDatabaseCount('branch_transfer_approvals', 2);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id,
            'to_status' => 'in_transit',
        ]);
    }

    public function test_submitting_existing_draft_also_records_active_branch_approval(): void
    {
        $fixture = $this->transferFixture();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture))
            ->assertSessionHasNoErrors();

        $transfer = BranchTransfer::query()->firstOrFail();
        $this->assertSame('draft', $transfer->status->value);
        $this->assertDatabaseCount('branch_transfer_approvals', 0);

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.submit', $transfer))
            ->assertSessionHasNoErrors();

        $this->assertSame('pending_approval', $transfer->fresh()->status->value);
        $this->assertDatabaseHas('branch_transfer_approvals', [
            'branch_transfer_id' => $transfer->id,
            'revision_number' => 1,
            'side' => 'origin',
            'decision' => 'approved',
            'decided_by' => $fixture['originManager']->id,
        ]);
    }

    public function test_same_account_cannot_approve_both_sides(): void
    {
        $fixture = $this->transferFixture();
        $fixture['originManager']->branches()->attach($fixture['destination']->id, [
            'is_default' => false,
            'is_active' => true,
        ]);
        $managerRole = Role::query()
            ->where('company_id', $fixture['destination']->company_id)
            ->where('slug', 'branch-manager')
            ->firstOrFail();
        $fixture['originManager']->roles()->attach($managerRole->id, [
            'branch_id' => $fixture['destination']->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->firstOrFail();
        $fixture['originManager']->update(['current_branch_id' => $fixture['destination']->id]);

        $this->actingAs($fixture['originManager']->fresh())
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Mencoba approval ganda.',
                'revision_number' => 1,
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending_approval', $transfer->fresh()->status->value);
        $this->assertDatabaseCount('branch_transfer_approvals', 1);
    }

    public function test_material_edit_after_approval_releases_hold_and_requires_new_revision(): void
    {
        $fixture = $this->transferFixture();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Siap menerima.',
                'revision_number' => 1,
            ]);

        $payload = $this->serializedPayload($fixture);
        $payload['reason'] = 'Pemerataan utilisasi aset dengan alasan revisi.';
        $payload['lock_version'] = $transfer->fresh()->lock_version;

        $this->actingAs($fixture['originManager'])
            ->put(route('transfers.update', $transfer), $payload)
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame(2, $transfer->revision_number);
        $this->assertSame('pending_approval', $transfer->status->value);
        $this->assertSame('available', $fixture['asset']->fresh()->status);
        $this->assertDatabaseCount('branch_transfer_approvals', 2);
    }

    public function test_non_material_edit_keeps_approval_and_held_item_identity(): void
    {
        $fixture = $this->transferFixture();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Siap menerima.',
                'revision_number' => 1,
            ]);

        $transfer->refresh();
        $item = $transfer->items()->firstOrFail();
        $payload = $this->serializedPayload($fixture);
        $payload['waybill_number'] = 'SJ-NON-MATERIAL-001';
        $payload['lock_version'] = $transfer->lock_version;

        $this->actingAs($fixture['originManager'])
            ->put(route('transfers.update', $transfer), $payload)
            ->assertSessionHasNoErrors();

        $transfer->refresh();
        $this->assertSame('approved', $transfer->status->value);
        $this->assertSame(1, $transfer->revision_number);
        $this->assertSame($item->id, $transfer->items()->firstOrFail()->id);
        $this->assertSame('held', $transfer->items()->firstOrFail()->status->value);
        $this->assertSame('in_transit', $fixture['asset']->fresh()->status);
        $this->assertDatabaseCount('branch_transfer_approvals', 2);
    }
}
