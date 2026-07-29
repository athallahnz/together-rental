<?php

namespace Tests\Feature\Maintenance;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\MaintenanceOrder;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_report_start_and_complete_maintenance_atomically(): void
    {
        [$user, $asset] = $this->fixture('branch-manager');

        $this->actingAs($user)->post(route('maintenance.store'), [
            'asset_id' => $asset->id,
            'type' => 'repair',
            'problem_description' => 'Shutter tidak dapat ditekan.',
            'vendor_name' => 'Kamera Sehat',
            'estimated_cost' => 250000,
        ])->assertSessionHasNoErrors();

        $maintenance = MaintenanceOrder::query()->firstOrFail();
        $this->assertSame('maintenance', $asset->fresh()->status);
        $this->assertSame(1, BranchInventory::query()->firstOrFail()->quantity_maintenance);
        $this->assertDatabaseHas('activity_logs', ['event' => 'maintenance.reported']);

        $this->actingAs($user)->post(route('maintenance.start', $maintenance))
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $maintenance->fresh()->status);

        $this->actingAs($user)->post(route('maintenance.complete', $maintenance), [
            'resolution' => 'Shutter diganti dan pengujian selesai.',
            'actual_cost' => 225000,
            'asset_condition' => 'good',
            'asset_disposition' => 'available',
        ])->assertSessionHasNoErrors();

        $maintenance->refresh();
        $this->assertSame('completed', $maintenance->status);
        $this->assertSame('225000.00', $maintenance->actual_cost);
        $this->assertNotNull($maintenance->completed_at);
        $this->assertSame('available', $asset->fresh()->status);
        $this->assertSame('good', $asset->fresh()->condition);
        $this->assertSame(0, BranchInventory::query()->firstOrFail()->quantity_maintenance);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id,
            'source_type' => MaintenanceOrder::class,
            'source_id' => $maintenance->id,
            'to_status' => 'available',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'maintenance.completed']);
    }

    public function test_active_duplicate_and_invalid_available_disposition_are_rejected(): void
    {
        [$user, $asset] = $this->fixture('branch-manager');
        MaintenanceOrder::query()->create([
            'branch_id' => $asset->current_branch_id,
            'asset_id' => $asset->id,
            'maintenance_number' => 'MNT-PNG-TEST-0001',
            'type' => 'repair',
            'status' => 'reported',
            'problem_description' => 'Kerusakan pertama.',
            'reported_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('maintenance.store'), [
            'asset_id' => $asset->id,
            'type' => 'repair',
            'problem_description' => 'Duplikasi pekerjaan.',
            'estimated_cost' => 100000,
        ])->assertSessionHasErrors('asset_id');
        $this->assertDatabaseCount('maintenance_orders', 1);

        $maintenance = MaintenanceOrder::query()->firstOrFail();
        $this->actingAs($user)->post(route('maintenance.complete', $maintenance), [
            'resolution' => 'Belum berhasil diperbaiki.',
            'actual_cost' => 50000,
            'asset_condition' => 'damaged',
            'asset_disposition' => 'available',
        ])->assertSessionHasErrors('asset_condition');
        $this->assertSame('reported', $maintenance->fresh()->status);
    }

    public function test_branch_user_cannot_access_foreign_maintenance(): void
    {
        [$user, $asset] = $this->fixture('branch-manager');
        $foreign = Branch::query()->create([
            'company_id' => $asset->currentBranch->company_id,
            'code' => 'SBY',
            'name' => 'Together Surabaya',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
        ]);
        $foreignAsset = $asset->replicate();
        $foreignAsset->forceFill([
            'owning_branch_id' => $foreign->id,
            'current_branch_id' => $foreign->id,
            'asset_code' => 'SBY-A001',
        ])->save();
        $maintenance = MaintenanceOrder::query()->create([
            'branch_id' => $foreign->id,
            'asset_id' => $foreignAsset->id,
            'maintenance_number' => 'MNT-SBY-TEST-0001',
            'type' => 'service',
            'status' => 'reported',
            'problem_description' => 'Servis cabang lain.',
            'reported_at' => now(),
        ]);

        $this->actingAs($user)->get(route('maintenance.show', $maintenance))->assertNotFound();
        $this->actingAs($user)->post(route('maintenance.start', $maintenance))->assertNotFound();
    }

    /** @return array{User, Asset} */
    private function fixture(string $roleSlug): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
        ]);
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach(
            Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            ['branch_id' => $branch->id, 'assigned_at' => now()],
        );
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-MNT-01',
            'name' => 'Canon Maintenance Test',
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-MNT-A001',
            'status' => 'available',
            'condition' => 'good',
        ]);
        BranchInventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 1,
        ]);

        return [$user, $asset];
    }
}
