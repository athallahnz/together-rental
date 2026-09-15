<?php

namespace Tests\Feature\Assets;

use App\Domain\Operations\OperationalDataResetService;
use App\Models\Asset;
use App\Models\AssetAcquisition;
use App\Models\AssetDisposal;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_acquire_serialized_assets_and_inventory_is_synchronized(): void
    {
        $fixture = $this->fixture('branch-manager');

        $this->actingAs($fixture['user'])
            ->post(route('assets.acquisitions.store'), [
                'branch_id' => $fixture['branch']->id,
                'product_id' => $fixture['product']->id,
                'quantity' => 2,
                'acquisition_date' => now()->toDateString(),
                'vendor_name' => 'PT Kamera Baru',
                'reference_number' => 'INV-ACQ-001',
                'unit_cost' => 15000000,
                'replacement_value' => 19000000,
                'warranty_until' => now()->addYear()->toDateString(),
                'serial_numbers' => "SN-A001\nSN-A002",
                'notes' => 'Pembelian dua body kamera.',
            ])
            ->assertSessionHasNoErrors();

        $acquisition = AssetAcquisition::query()->firstOrFail();
        $assets = Asset::query()->orderBy('id')->get();
        $this->assertCount(2, $assets);
        $this->assertSame('30000000.00', $acquisition->total_amount);
        $this->assertSame('available', $assets[0]->status);
        $this->assertSame('15000000.00', $assets[0]->purchase_price);
        $this->assertSame('SN-A001', $assets[0]->serial_number);
        $this->assertStringStartsWith('AST-PNG-', $assets[0]->asset_code);
        $this->assertSame(2, BranchInventory::query()->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseCount('asset_acquisition_items', 2);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $assets[0]->id,
            'source_type' => AssetAcquisition::class,
            'source_id' => $acquisition->id,
            'from_status' => null,
            'to_status' => 'available',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'asset.acquired',
            'subject_type' => AssetAcquisition::class,
            'subject_id' => $acquisition->id,
        ]);
    }

    public function test_branch_scope_blocks_foreign_acquisition_and_lifecycle_view(): void
    {
        $fixture = $this->fixture('branch-manager');
        $foreign = $this->foreignBranch($fixture['branch']);

        $this->actingAs($fixture['user'])
            ->post(route('assets.acquisitions.store'), [
                'branch_id' => $foreign->id,
                'product_id' => $fixture['product']->id,
                'quantity' => 1,
                'acquisition_date' => now()->toDateString(),
                'unit_cost' => 1000000,
            ])
            ->assertNotFound();

        $this->actingAs($fixture['user'])
            ->get(route('assets.lifecycle.index', ['branch_id' => $foreign->id]))
            ->assertForbidden();
        $this->assertDatabaseCount('asset_acquisitions', 0);
    }

    public function test_asset_can_be_sold_and_becomes_retired_without_deleting_history(): void
    {
        $fixture = $this->fixture('branch-manager');
        $asset = $this->acquiredAsset($fixture);

        $this->actingAs($fixture['user'])
            ->post(route('assets.disposals.store', $asset), [
                'disposal_date' => now()->toDateString(),
                'method' => 'sold',
                'sale_amount' => 8000000,
                'reason' => 'Upgrade body kamera ke generasi baru.',
            ])
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $disposal = AssetDisposal::query()->firstOrFail();
        $this->assertSame('retired', $asset->status);
        $this->assertFalse($asset->is_active);
        $this->assertSame('8000000.00', $disposal->sale_amount);
        $this->assertSame(0, BranchInventory::query()->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id,
            'source_type' => AssetDisposal::class,
            'source_id' => $disposal->id,
            'from_status' => 'available',
            'to_status' => 'retired',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'asset.disposed',
            'subject_type' => AssetDisposal::class,
            'subject_id' => $disposal->id,
        ]);
    }

    public function test_disposal_is_blocked_while_asset_is_not_operationally_free(): void
    {
        $fixture = $this->fixture('branch-manager');
        $asset = $this->acquiredAsset($fixture);
        $asset->forceFill(['status' => 'rented'])->save();

        $this->actingAs($fixture['user'])
            ->post(route('assets.disposals.store', $asset), [
                'disposal_date' => now()->toDateString(),
                'method' => 'write_off',
                'sale_amount' => 0,
                'reason' => 'Percobaan disposal saat aset masih dipakai.',
            ])
            ->assertSessionHasErrors('asset');

        $this->assertTrue($asset->fresh()->is_active);
        $this->assertDatabaseCount('asset_disposals', 0);
    }

    public function test_owner_management_can_view_but_cannot_mutate_asset_lifecycle(): void
    {
        $fixture = $this->fixture('owner-management', companyScoped: true);

        $this->actingAs($fixture['user'])
            ->get(route('assets.lifecycle.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('assets/lifecycle')
                ->where('permissions.manage', false));

        $this->actingAs($fixture['user'])
            ->post(route('assets.acquisitions.store'), [
                'branch_id' => $fixture['branch']->id,
                'product_id' => $fixture['product']->id,
                'quantity' => 1,
                'acquisition_date' => now()->toDateString(),
                'unit_cost' => 1000000,
            ])
            ->assertForbidden();
    }

    public function test_operational_reset_preserves_lifecycle_status_history(): void
    {
        $fixture = $this->fixture('branch-manager');
        $asset = $this->acquiredAsset($fixture);
        $acquisition = AssetAcquisition::query()->firstOrFail();

        app(OperationalDataResetService::class)->reset(
            (int) $fixture['branch']->company_id,
            $fixture['branch'],
            false,
        );

        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $asset->id,
            'source_type' => AssetAcquisition::class,
            'source_id' => $acquisition->id,
            'to_status' => 'available',
        ]);
        $this->assertDatabaseHas('asset_acquisitions', ['id' => $acquisition->id]);
    }

    /** @return array{user: User, branch: Branch, product: Product} */
    private function fixture(string $roleSlug, bool $companyScoped = false): array
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
            ['branch_id' => $companyScoped ? null : $branch->id, 'assigned_at' => now()],
        );
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-LIFE-01',
            'name' => 'Sony Lifecycle Test',
            'tracking_type' => 'serialized',
            'replacement_value' => 19000000,
            'is_active' => true,
        ]);

        return compact('user', 'branch', 'product');
    }

    /** @param array{user: User, branch: Branch, product: Product} $fixture */
    private function acquiredAsset(array $fixture): Asset
    {
        $this->actingAs($fixture['user'])
            ->post(route('assets.acquisitions.store'), [
                'branch_id' => $fixture['branch']->id,
                'product_id' => $fixture['product']->id,
                'quantity' => 1,
                'acquisition_date' => now()->toDateString(),
                'unit_cost' => 15000000,
                'replacement_value' => 19000000,
                'serial_numbers' => 'SN-LIFE-001',
            ])
            ->assertSessionHasNoErrors();

        return Asset::query()->firstOrFail();
    }

    private function foreignBranch(Branch $branch): Branch
    {
        return Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
        ]);
    }
}
