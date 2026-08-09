<?php

namespace Tests\Feature\Transfers;

use App\Models\Asset;
use App\Models\AssetInspectionMedia;
use App\Models\Branch;
use App\Models\BranchTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchTransferMediaSettingsTest extends TestCase
{
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_camera_required_policy_rejects_gallery_dispatch_without_override(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true));
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima.',
                'revision_number' => 1,
            ]);
        $item = $transfer->items()->firstOrFail();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.dispatch.store', $transfer), [
                'shipping_method' => 'internal',
                'courier_name' => 'Tim Internal',
                'waybill_number' => 'SJ-CAMERA-001',
                'inspections' => [[
                    'item_id' => $item->id,
                    'condition' => 'good',
                    'capture_source' => 'gallery',
                    'photos' => [UploadedFile::fake()->image('gallery.jpg')],
                ]],
            ])
            ->assertSessionHasErrors('inspections');

        $this->assertSame('approved', $transfer->fresh()->status->value);
        $this->assertDatabaseCount('asset_inspections', 0);
        $this->assertDatabaseCount('asset_inspection_media', 0);
        $this->assertDatabaseCount('branch_transfer_documents', 0);
    }

    public function test_super_admin_can_update_transfer_capture_policy_per_branch(): void
    {
        $fixture = $this->transferFixture();
        $superAdmin = $this->branchUser(
            $fixture['origin'],
            'super-admin',
            'transfer.superadmin@example.test',
        );

        $this->actingAs($superAdmin)
            ->put(route('transfers.settings.update'), [
                'branch_id' => $fixture['origin']->id,
                'dispatch_capture_mode' => 'camera_preferred',
                'receiving_capture_mode' => 'camera_required',
                'dispatch_min_photos' => 2,
                'receiving_min_photos' => 3,
                'require_waybill' => true,
                'allow_gallery_override' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '"camera_preferred"',
            DB::table('branch_settings')
                ->where('branch_id', $fixture['origin']->id)
                ->where('key', 'transfer_dispatch_capture_mode')
                ->value('value'),
        );
        $this->assertSame(
            '2',
            DB::table('branch_settings')
                ->where('branch_id', $fixture['origin']->id)
                ->where('key', 'transfer_dispatch_min_photos')
                ->value('value'),
        );
        $this->assertSame(
            'true',
            DB::table('branch_settings')
                ->where('branch_id', $fixture['origin']->id)
                ->where('key', 'transfer_allow_gallery_override')
                ->value('value'),
        );
    }

    public function test_failed_multi_item_dispatch_removes_files_written_before_rollback(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();
        $secondAsset = Asset::query()->create([
            'product_id' => $fixture['product']->id,
            'owning_branch_id' => $fixture['origin']->id,
            'current_branch_id' => $fixture['origin']->id,
            'asset_code' => 'PNG-TRF-002',
            'serial_number' => 'SERIAL-TRF-002',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);
        $payload = $this->serializedPayload($fixture, true);
        $payload['items'][] = [
            'product_id' => $fixture['product']->id,
            'asset_id' => $secondAsset->id,
            'quantity' => 1,
            'condition_before' => 'good',
            'notes' => 'Unit kedua.',
        ];

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $payload)
            ->assertSessionHasNoErrors();
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima dua unit.',
                'revision_number' => 1,
            ])
            ->assertSessionHasNoErrors();
        $firstItem = $transfer->items()->orderBy('line_number')->firstOrFail();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.dispatch.store', $transfer), [
                'shipping_method' => 'internal',
                'courier_name' => 'Tim Internal',
                'waybill_number' => 'SJ-ROLLBACK-001',
                'inspections' => [[
                    'item_id' => $firstItem->id,
                    'condition' => 'good',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('first-item.jpg')],
                ]],
            ])
            ->assertSessionHasErrors('inspections');

        $this->assertSame('approved', $transfer->fresh()->status->value);
        $this->assertDatabaseCount('asset_inspections', 0);
        $this->assertDatabaseCount('asset_inspection_media', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_transfer_inspection_media_is_private_to_involved_branches(): void
    {
        Storage::fake('local');
        $fixture = $this->transferFixture();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture, true))
            ->assertSessionHasNoErrors();
        $transfer = BranchTransfer::query()->firstOrFail();
        $this->actingAs($fixture['destinationManager'])
            ->post(route('transfers.approvals.store', $transfer), [
                'side' => 'destination',
                'decision' => 'approved',
                'notes' => 'Cabang tujuan siap menerima.',
                'revision_number' => 1,
            ])
            ->assertSessionHasNoErrors();
        $item = $transfer->items()->firstOrFail();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.dispatch.store', $transfer), [
                'shipping_method' => 'internal',
                'courier_name' => 'Tim Internal',
                'waybill_number' => 'SJ-PRIVATE-001',
                'inspections' => [[
                    'item_id' => $item->id,
                    'condition' => 'good',
                    'capture_source' => 'camera',
                    'photos' => [UploadedFile::fake()->image('private-evidence.jpg')],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $media = AssetInspectionMedia::query()->firstOrFail();
        $this->actingAs($fixture['originManager'])
            ->get(route('transfers.inspection-media.show', $media))
            ->assertOk();

        $unrelatedBranch = Branch::query()->create([
            'company_id' => $fixture['origin']->company_id,
            'code' => 'SBY',
            'name' => 'Together Kamera Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $unrelatedManager = $this->branchUser(
            $unrelatedBranch,
            'branch-manager',
            'unrelated.manager@example.test',
        );

        $this->actingAs($unrelatedManager)
            ->get(route('transfers.inspection-media.show', $media))
            ->assertNotFound();
    }
}
