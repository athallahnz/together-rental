<?php

namespace Tests\Feature\Transfers;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchTransferFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_transfer_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('branch_transfers', [
            'revision_number',
            'lock_version',
            'planned_dispatch_at',
            'expected_arrival_at',
            'waybill_number',
            'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('branch_transfer_items', [
            'line_number',
            'previous_branch_id',
            'previous_asset_status',
            'received_quantity',
            'receiving_result',
            'resolution_action',
        ]));
        $this->assertTrue(Schema::hasTable('branch_transfer_approvals'));
        $this->assertTrue(Schema::hasTable('branch_transfer_expenses'));
        $this->assertTrue(Schema::hasTable('branch_transfer_documents'));
        $this->assertTrue(Schema::hasColumn('branch_inventories', 'quantity_in_transfer'));
        $this->assertTrue(Schema::hasColumn('asset_inspections', 'branch_transfer_item_id'));
    }

    public function test_foundation_permissions_follow_default_business_decisions(): void
    {
        $this->seed(RentalFoundationSeeder::class);

        foreach ([
            'transfers.update',
            'transfers.cancel',
            'transfers.dispatch',
            'transfers.expense',
            'transfers.resolve_discrepancy',
            'transfers.settings',
            'transfers.override',
        ] as $slug) {
            $this->assertTrue(Permission::query()->where('slug', $slug)->exists());
        }

        $manager = Role::query()->where('slug', 'branch-manager')->firstOrFail();
        $inventory = Role::query()->where('slug', 'inventory-staff')->firstOrFail();

        $this->assertTrue($manager->permissions()->where('slug', 'transfers.create')->exists());
        $this->assertTrue($manager->permissions()->where('slug', 'transfers.approve')->exists());
        $this->assertFalse($inventory->permissions()->where('slug', 'transfers.create')->exists());
        $this->assertTrue($inventory->permissions()->where('slug', 'transfers.dispatch')->exists());
        $this->assertTrue($inventory->permissions()->where('slug', 'transfers.receive')->exists());
    }
}
