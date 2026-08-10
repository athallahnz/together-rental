<?php

namespace Tests\Feature\InventoryAudits;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\InventoryAuditMedia;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryAuditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_staff_can_create_a_branch_snapshot_for_serialized_and_quantity_stock(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['staff'])
            ->post(route('inventory-audits.store'), [
                'branch_id' => $fixture['branch']->id,
                'title' => 'Stock Opname Bulanan Agustus',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'notes' => 'Periksa area display dan penyimpanan.',
            ])
            ->assertSessionHasNoErrors();

        $audit = InventoryAudit::query()->firstOrFail();
        $this->assertSame('draft', $audit->status);
        $this->assertSame(3, $audit->snapshot_item_count);
        $this->assertDatabaseHas('inventory_audit_items', [
            'inventory_audit_id' => $audit->id,
            'asset_id' => $fixture['availableAsset']->id,
            'expected_quantity' => 1,
        ]);
        $this->assertDatabaseHas('inventory_audit_items', [
            'inventory_audit_id' => $audit->id,
            'asset_id' => $fixture['rentedAsset']->id,
            'expected_quantity' => 0,
        ]);
        $this->assertDatabaseHas('inventory_audit_items', [
            'inventory_audit_id' => $audit->id,
            'product_id' => $fixture['quantityProduct']->id,
            'asset_id' => null,
            'expected_quantity' => 7,
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'inventory_audit.created']);

        $this->actingAs($fixture['staff'])
            ->get(route('inventory-audits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory-audits/index')
                ->has('audits.data', 1)
                ->where('audits.data.0.audit_number', $audit->audit_number)
                ->where('permissions.create', true)
                ->where('permissions.approve', false));

        $this->actingAs($fixture['staff'])
            ->get(route('reports.index', [
                'report' => 'inventory-audits',
                'from' => now()->toDateString(),
                'to' => now()->addDays(2)->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->where('reportMeta.key', 'inventory-audits')
                ->where('reportMeta.row_count', 1)
                ->where('rows.data.0.href', '/inventory-audits/'.$audit->id));
    }

    public function test_only_one_active_audit_is_allowed_per_branch(): void
    {
        $fixture = $this->fixture();
        $this->createAudit($fixture, $fixture['staff']);

        $this->actingAs($fixture['staff'])
            ->post(route('inventory-audits.store'), [
                'branch_id' => $fixture['branch']->id,
                'title' => 'Stock Opname Duplikat Cabang',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount('inventory_audits', 1);
    }

    public function test_all_items_must_be_counted_before_submission(): void
    {
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.start', $audit))
            ->assertSessionHasNoErrors();

        $availableItem = $audit->items()->where('asset_id', $fixture['availableAsset']->id)->firstOrFail();
        $this->recordMatched($fixture['manager'], $audit, $availableItem);

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.submit', $audit))
            ->assertSessionHasErrors('inventory_audit');
        $this->assertSame('in_progress', $audit->fresh()->status);

        $this->recordRemainingMatched($fixture['manager'], $audit);
        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.submit', $audit))
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $audit->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['event' => 'inventory_audit.submitted']);
    }

    public function test_discrepancy_requires_realtime_camera_evidence_and_private_media_is_scoped(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);
        $this->actingAs($fixture['manager'])->post(route('inventory-audits.start', $audit));
        $item = $audit->items()->where('asset_id', $fixture['availableAsset']->id)->firstOrFail();

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.items.count', [$audit, $item]), [
                'counted_quantity' => 0,
                'capture_source' => 'camera',
                'notes' => 'Unit tidak ditemukan pada rak penyimpanan.',
            ])
            ->assertSessionHasErrors('photos');
        $this->assertSame('pending', $item->fresh()->finding_status);

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.items.count', [$audit, $item]), [
                'counted_quantity' => 0,
                'capture_source' => 'camera',
                'notes' => 'Unit tidak ditemukan pada rak penyimpanan.',
                'photos' => [UploadedFile::fake()->image('rak-kosong.jpg', 1200, 800)],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $media = InventoryAuditMedia::query()->firstOrFail();
        $this->assertSame('missing', $item->finding_status);
        $this->assertSame('camera', $media->capture_source);
        Storage::disk('local')->assertExists($media->path);

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.items.count', [$audit, $item]), [
                'counted_quantity' => 0,
                'capture_source' => 'camera',
                'notes' => 'Pencatatan ulang tetap wajib bukti terbaru.',
            ])
            ->assertSessionHasErrors('photos');

        $this->actingAs($fixture['manager'])
            ->get(route('inventory-audits.media', $media))
            ->assertOk();

        $this->actingAs($fixture['foreignManager'])
            ->get(route('inventory-audits.media', $media))
            ->assertNotFound();
    }

    public function test_submitter_cannot_approve_own_result_but_second_manager_can(): void
    {
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);
        $this->prepareSubmittedAudit($fixture, $audit, $fixture['manager']);

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.approve', $audit), ['approval_notes' => 'Sesuai.'])
            ->assertSessionHasErrors('inventory_audit');
        $this->assertSame('submitted', $audit->fresh()->status);

        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.approve', $audit), ['approval_notes' => 'Verifikasi kedua selesai.'])
            ->assertSessionHasNoErrors();

        $audit->refresh();
        $this->assertSame('approved', $audit->status);
        $this->assertSame($fixture['approver']->id, $audit->approved_by);
        $this->assertDatabaseHas('activity_logs', ['event' => 'inventory_audit.approved']);
    }

    public function test_scanned_foreign_asset_is_flagged_without_bypassing_transfer_workflow(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);
        $this->actingAs($fixture['manager'])->post(route('inventory-audits.start', $audit));

        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.scan', $audit), [
                'code' => $fixture['foreignAsset']->asset_code,
            ])
            ->assertSessionHasNoErrors();

        $item = $audit->items()->where('asset_id', $fixture['foreignAsset']->id)->firstOrFail();
        $this->actingAs($fixture['manager'])
            ->post(route('inventory-audits.items.count', [$audit, $item]), [
                'counted_quantity' => 1,
                'observed_status' => 'available',
                'observed_condition' => 'good',
                'capture_source' => 'camera',
                'notes' => 'Unit cabang lain ditemukan pada rak.',
                'photos' => [UploadedFile::fake()->image('salah-lokasi.jpg', 1200, 800)],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('unexpected', $item->finding_status);
        $this->assertContains('wrong_branch', $item->issue_flags);
        $this->assertSame($fixture['otherBranch']->id, $fixture['foreignAsset']->fresh()->current_branch_id);
    }

    public function test_approved_findings_can_adjust_quantity_mark_missing_asset_lost_and_close_audit(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);
        $this->actingAs($fixture['manager'])->post(route('inventory-audits.start', $audit));

        $missing = $audit->items()->where('asset_id', $fixture['availableAsset']->id)->firstOrFail();
        $quantity = $audit->items()->where('product_id', $fixture['quantityProduct']->id)->firstOrFail();
        $rented = $audit->items()->where('asset_id', $fixture['rentedAsset']->id)->firstOrFail();

        $this->recordFinding($fixture['manager'], $audit, $missing, 0, 'missing.jpg');
        $this->recordFinding($fixture['manager'], $audit, $quantity, 5, 'quantity.jpg');
        $this->recordMatched($fixture['manager'], $audit, $rented);
        $this->actingAs($fixture['manager'])->post(route('inventory-audits.submit', $audit));

        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.approve', $audit), ['approval_notes' => ''])
            ->assertSessionHasErrors('approval_notes');
        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.approve', $audit), [
                'approval_notes' => 'Temuan telah diverifikasi bersama petugas inventaris.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.close', $audit))
            ->assertSessionHasErrors('inventory_audit');

        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.items.resolve', [$audit, $missing]), [
                'resolution_action' => 'mark_lost',
                'resolution_notes' => 'Unit dinyatakan hilang setelah penelusuran fisik dan log selesai.',
            ])
            ->assertSessionHasNoErrors();
        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.items.resolve', [$audit, $quantity]), [
                'resolution_action' => 'adjust_quantity',
                'resolution_notes' => 'Stok fisik disesuaikan berdasarkan hitung ulang bersama manager.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('lost', $fixture['availableAsset']->fresh()->status);
        $this->assertSame(8, $fixture['quantityInventory']->fresh()->quantity_on_hand);

        $this->actingAs($fixture['approver'])
            ->post(route('inventory-audits.close', $audit))
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $audit->fresh()->status);
        $this->assertDatabaseHas('asset_status_histories', [
            'asset_id' => $fixture['availableAsset']->id,
            'source_type' => InventoryAuditItem::class,
            'source_id' => $missing->id,
            'to_status' => 'lost',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'inventory_audit.closed']);
    }

    public function test_branch_isolation_and_permission_separation_are_enforced(): void
    {
        $fixture = $this->fixture();
        $audit = $this->createAudit($fixture, $fixture['manager']);

        $this->actingAs($fixture['foreignManager'])
            ->get(route('inventory-audits.show', $audit))
            ->assertNotFound();
        $this->actingAs($fixture['foreignManager'])
            ->post(route('inventory-audits.start', $audit))
            ->assertNotFound();

        $this->actingAs($fixture['staff'])
            ->post(route('inventory-audits.approve', $audit), ['approval_notes' => 'Tidak berwenang.'])
            ->assertForbidden();
        $this->assertSame('draft', $audit->fresh()->status);
    }

    /**
     * @return array{
     *     branch: Branch,
     *     otherBranch: Branch,
     *     staff: User,
     *     manager: User,
     *     approver: User,
     *     foreignManager: User,
     *     availableAsset: Asset,
     *     rentedAsset: Asset,
     *     foreignAsset: Asset,
     *     quantityProduct: Product,
     *     quantityInventory: BranchInventory
     * }
     */
    private function fixture(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $otherBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $staff = $this->branchUser($branch, 'inventory-staff', 'audit.staff@example.test');
        $manager = $this->branchUser($branch, 'branch-manager', 'audit.manager@example.test');
        $approver = $this->branchUser($branch, 'branch-manager', 'audit.approver@example.test');
        $foreignManager = $this->branchUser($otherBranch, 'branch-manager', 'foreign.manager@example.test');

        $serialized = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'AUD-CAM-001',
            'name' => 'Kamera Audit Test',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        $availableAsset = $this->asset($serialized, $branch, 'PNG-AUD-001', 'available');
        $rentedAsset = $this->asset($serialized, $branch, 'PNG-AUD-002', 'rented');
        $foreignAsset = $this->asset($serialized, $otherBranch, 'MDN-AUD-001', 'available');
        BranchInventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $serialized->id,
            'quantity_on_hand' => 2,
            'quantity_rented' => 1,
        ]);

        $quantityProduct = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'AUD-ACC-001',
            'name' => 'Aksesori Audit Test',
            'tracking_type' => 'quantity',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        $quantityInventory = BranchInventory::query()->create([
            'branch_id' => $branch->id,
            'product_id' => $quantityProduct->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 1,
            'quantity_rented' => 2,
            'quantity_maintenance' => 1,
            'quantity_in_transfer' => 1,
        ]);

        return compact(
            'branch',
            'otherBranch',
            'staff',
            'manager',
            'approver',
            'foreignManager',
            'availableAsset',
            'rentedAsset',
            'foreignAsset',
            'quantityProduct',
            'quantityInventory',
        );
    }

    /** @param array<string, mixed> $fixture */
    private function createAudit(array $fixture, User $actor): InventoryAudit
    {
        $this->actingAs($actor)
            ->post(route('inventory-audits.store'), [
                'branch_id' => $fixture['branch']->id,
                'title' => 'Stock Opname Integration Test',
                'notes' => 'Snapshot untuk integration test.',
            ])
            ->assertSessionHasNoErrors();

        return InventoryAudit::query()->firstOrFail();
    }

    /** @param array<string, mixed> $fixture */
    private function prepareSubmittedAudit(array $fixture, InventoryAudit $audit, User $actor): void
    {
        $this->actingAs($actor)->post(route('inventory-audits.start', $audit));
        $this->recordRemainingMatched($actor, $audit);
        $this->actingAs($actor)
            ->post(route('inventory-audits.submit', $audit))
            ->assertSessionHasNoErrors();
    }

    private function recordRemainingMatched(User $actor, InventoryAudit $audit): void
    {
        $audit->items()
            ->where('finding_status', 'pending')
            ->get()
            ->each(fn (InventoryAuditItem $item) => $this->recordMatched($actor, $audit, $item));
    }

    private function recordMatched(User $actor, InventoryAudit $audit, InventoryAuditItem $item): void
    {
        $payload = [
            'counted_quantity' => $item->expected_quantity,
            'capture_source' => 'camera',
            'notes' => 'Hasil sesuai snapshot.',
        ];

        if ($item->tracking_type === 'serialized' && $item->expected_quantity === 1) {
            $payload['observed_status'] = $item->expected_status;
            $payload['observed_condition'] = $item->expected_condition;
        }

        $this->actingAs($actor)
            ->post(route('inventory-audits.items.count', [$audit, $item]), $payload)
            ->assertSessionHasNoErrors();
    }

    private function recordFinding(
        User $actor,
        InventoryAudit $audit,
        InventoryAuditItem $item,
        int $quantity,
        string $filename,
    ): void {
        $this->actingAs($actor)
            ->post(route('inventory-audits.items.count', [$audit, $item]), [
                'counted_quantity' => $quantity,
                'capture_source' => 'camera',
                'notes' => 'Temuan dikonfirmasi melalui hitung ulang.',
                'photos' => [UploadedFile::fake()->image($filename, 1200, 800)],
            ])
            ->assertSessionHasNoErrors();
    }

    private function asset(Product $product, Branch $branch, string $code, string $status): Asset
    {
        return Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => $code,
            'serial_number' => 'SERIAL-'.$code,
            'status' => $status,
            'condition' => 'good',
            'is_active' => true,
        ]);
    }

    private function branchUser(Branch $branch, string $roleSlug, string $email): User
    {
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email' => $email,
            'email_verified_at' => now(),
        ]);
        $role = Role::query()
            ->where('company_id', $branch->company_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'company' ? null : $branch->id,
            'assigned_at' => now(),
        ]);

        return $user;
    }
}
