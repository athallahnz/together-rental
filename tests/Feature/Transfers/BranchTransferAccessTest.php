<?php

namespace Tests\Feature\Transfers;

use App\Models\Branch;
use App\Models\BranchTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferAccessTest extends TestCase
{
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_inventory_staff_can_dispatch_but_cannot_create_or_approve(): void
    {
        $fixture = $this->transferFixture();

        $this->actingAs($fixture['inventoryStaff'])
            ->get(route('transfers.create'))
            ->assertForbidden();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->firstOrFail();

        $this->actingAs($fixture['inventoryStaff'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'origin',
                'decision' => 'approved',
                'notes' => 'Tidak seharusnya bisa.',
                'revision_number' => 1,
            ])
            ->assertForbidden();
    }

    public function test_destination_manager_can_view_incoming_transfer_but_unrelated_branch_cannot(): void
    {
        $fixture = $this->transferFixture();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture));
        $transfer = BranchTransfer::query()->firstOrFail();

        $this->actingAs($fixture['destinationManager'])
            ->get(route('transfers.show', $transfer))
            ->assertOk();

        $other = Branch::query()->create([
            'company_id' => $fixture['origin']->company_id,
            'code' => 'KDR',
            'name' => 'Together Kamera Kediri',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $otherManager = $this->branchUser($other, 'branch-manager', 'other.manager@example.test');

        $this->actingAs($otherManager)
            ->get(route('transfers.show', $transfer))
            ->assertNotFound();
    }
}
