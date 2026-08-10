<?php

namespace App\Domain\InventoryAudits;

use App\Domain\Maintenance\MaintenanceManager;
use App\Domain\Transfers\TransferInventorySynchronizer;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAuditManager
{
    /** @var list<string> */
    private const PHYSICALLY_EXPECTED_STATUSES = ['available', 'reserved', 'maintenance'];

    /** @var list<string> */
    private const FINDINGS_REQUIRING_RESOLUTION = ['discrepancy', 'missing', 'unexpected'];

    public function __construct(
        private readonly InventoryAuditMediaManager $media,
        private readonly MaintenanceManager $maintenance,
        private readonly TransferInventorySynchronizer $inventory,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): InventoryAudit
    {
        return DB::transaction(function () use ($data, $actor): InventoryAudit {
            $branch = Branch::query()
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail((int) $data['branch_id']);
            $this->guardBranch($branch->id, $actor);

            if (InventoryAudit::query()
                ->where('branch_id', $branch->id)
                ->whereIn('status', ['draft', 'in_progress', 'submitted', 'approved'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Cabang masih memiliki stock opname aktif yang belum ditutup.',
                ]);
            }

            $audit = InventoryAudit::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $branch->id,
                'audit_number' => $this->nextNumber($branch),
                'title' => $data['title'],
                'status' => 'draft',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'snapshot_item_count' => 0,
                'lock_version' => 0,
                'created_by' => $actor->id,
            ]);

            $this->snapshotSerializedAssets($audit);
            $this->snapshotQuantityInventory($audit);
            $audit->forceFill(['snapshot_item_count' => $audit->items()->count()])->save();

            return $audit->fresh(['branch', 'items']);
        }, 3);
    }

    public function start(InventoryAudit $audit, User $actor): InventoryAudit
    {
        return DB::transaction(function () use ($audit, $actor): InventoryAudit {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Hanya draft stock opname yang dapat dimulai.',
                ]);
            }

            if ($locked->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Snapshot tidak memiliki item untuk dihitung.',
                ]);
            }

            $locked->forceFill([
                'status' => 'in_progress',
                'started_by' => $actor->id,
                'started_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordCount(
        InventoryAuditItem $item,
        array $data,
        User $actor,
    ): InventoryAuditItem {
        return $this->media->transactional(function () use ($item, $data, $actor): InventoryAuditItem {
            $audit = InventoryAudit::query()->lockForUpdate()->findOrFail($item->inventory_audit_id);
            $this->guardAudit($audit, $actor);

            if ($audit->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Pencatatan hanya tersedia saat stock opname sedang berjalan.',
                ]);
            }

            $locked = InventoryAuditItem::query()
                ->where('inventory_audit_id', $audit->id)
                ->lockForUpdate()
                ->findOrFail($item->id);
            $countedQuantity = (int) $data['counted_quantity'];

            if ($locked->tracking_type === 'serialized' && ! in_array($countedQuantity, [0, 1], true)) {
                throw ValidationException::withMessages([
                    'counted_quantity' => 'Unit serialized hanya dapat dicatat tidak ada atau satu unit.',
                ]);
            }

            if ($locked->tracking_type === 'serialized' && $countedQuantity === 1) {
                if (! is_string($data['observed_status'] ?? null)
                    || ! is_string($data['observed_condition'] ?? null)) {
                    throw ValidationException::withMessages([
                        'observed_condition' => 'Status dan kondisi aktual wajib dipilih saat unit ditemukan.',
                    ]);
                }
            }

            [$finding, $issues] = $this->finding(
                $locked,
                $audit,
                $countedQuantity,
                is_string($data['observed_status'] ?? null) ? $data['observed_status'] : null,
                is_string($data['observed_condition'] ?? null) ? $data['observed_condition'] : null,
            );
            $photos = $this->uploadedPhotos($data['photos'] ?? []);

            if (in_array($finding, self::FINDINGS_REQUIRING_RESOLUTION, true)
                && $photos === []) {
                throw ValidationException::withMessages([
                    'photos' => 'Setiap pencatatan temuan wajib memiliki bukti foto baru dari kamera realtime.',
                ]);
            }

            $locked->forceFill([
                'counted_quantity' => $countedQuantity,
                'finding_status' => $finding,
                'issue_flags' => $issues,
                'observed_status' => $countedQuantity === 0 ? null : ($data['observed_status'] ?? null),
                'observed_condition' => $countedQuantity === 0 ? null : ($data['observed_condition'] ?? null),
                'notes' => $data['notes'] ?? null,
                'counted_by' => $actor->id,
                'counted_at' => now(),
                'resolution_action' => null,
                'resolution_notes' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ])->save();

            $this->media->attachPhotos($locked, $photos, $actor);

            return $locked->fresh(['media', 'asset', 'product', 'expectedBranch']);
        });
    }

    public function addScannedAsset(
        InventoryAudit $audit,
        string $code,
        User $actor,
    ): InventoryAuditItem {
        return DB::transaction(function () use ($audit, $code, $actor): InventoryAuditItem {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'code' => 'Pemindaian unit hanya tersedia saat stock opname sedang berjalan.',
                ]);
            }

            $asset = Asset::query()
                ->where('is_active', true)
                ->whereHas('product', fn (Builder $query) => $query
                    ->where('company_id', $actor->company_id))
                ->where(function (Builder $query) use ($code): void {
                    $query->where('asset_code', $code)->orWhere('serial_number', $code);
                })
                ->first();

            if ($asset === null) {
                throw ValidationException::withMessages([
                    'code' => 'Kode aset atau serial number tidak ditemukan di perusahaan ini.',
                ]);
            }

            $existing = $locked->items()->where('asset_id', $asset->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $expectedHere = $asset->current_branch_id === $locked->branch_id
                && in_array($asset->status, self::PHYSICALLY_EXPECTED_STATUSES, true);
            $item = InventoryAuditItem::query()->create([
                'inventory_audit_id' => $locked->id,
                'product_id' => $asset->product_id,
                'asset_id' => $asset->id,
                'expected_branch_id' => $asset->current_branch_id,
                'tracking_type' => 'serialized',
                'expected_status' => $asset->status,
                'expected_condition' => $asset->condition,
                'expected_quantity' => $expectedHere ? 1 : 0,
                'finding_status' => 'pending',
            ]);
            $locked->forceFill([
                'snapshot_item_count' => $locked->snapshot_item_count + 1,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $item;
        }, 3);
    }

    public function submit(InventoryAudit $audit, User $actor): InventoryAudit
    {
        return DB::transaction(function () use ($audit, $actor): InventoryAudit {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Hanya stock opname berjalan yang dapat diajukan.',
                ]);
            }

            $pending = $locked->items()->where('finding_status', 'pending')->count();
            if ($pending > 0) {
                throw ValidationException::withMessages([
                    'inventory_audit' => "Masih ada {$pending} item yang belum dihitung.",
                ]);
            }

            $withoutEvidence = $locked->items()
                ->whereIn('finding_status', self::FINDINGS_REQUIRING_RESOLUTION)
                ->whereDoesntHave('media')
                ->count();
            if ($withoutEvidence > 0) {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Semua temuan wajib memiliki bukti foto kamera realtime.',
                ]);
            }

            $locked->forceFill([
                'status' => 'submitted',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    public function approve(
        InventoryAudit $audit,
        ?string $notes,
        User $actor,
    ): InventoryAudit {
        return DB::transaction(function () use ($audit, $notes, $actor): InventoryAudit {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Hanya hasil yang sudah diajukan yang dapat disetujui.',
                ]);
            }

            if ($locked->submitted_by === $actor->id) {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Petugas yang mengajukan tidak boleh menyetujui hasilnya sendiri.',
                ]);
            }

            $findings = $locked->items()
                ->whereIn('finding_status', self::FINDINGS_REQUIRING_RESOLUTION)
                ->count();
            if ($findings > 0 && trim((string) $notes) === '') {
                throw ValidationException::withMessages([
                    'approval_notes' => 'Catatan approval wajib diisi karena terdapat temuan selisih.',
                ]);
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function resolveFinding(
        InventoryAuditItem $item,
        array $data,
        User $actor,
    ): InventoryAuditItem {
        return DB::transaction(function () use ($item, $data, $actor): InventoryAuditItem {
            $audit = InventoryAudit::query()->lockForUpdate()->findOrFail($item->inventory_audit_id);
            $this->guardAudit($audit, $actor);

            if ($audit->status !== 'approved') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Temuan hanya dapat ditindaklanjuti setelah hasil disetujui.',
                ]);
            }

            $locked = InventoryAuditItem::query()
                ->where('inventory_audit_id', $audit->id)
                ->lockForUpdate()
                ->findOrFail($item->id);
            if (! in_array($locked->finding_status, self::FINDINGS_REQUIRING_RESOLUTION, true)) {
                throw ValidationException::withMessages([
                    'inventory_audit_item' => 'Item ini tidak memiliki temuan yang membutuhkan resolusi.',
                ]);
            }

            if ($locked->resolved_at !== null) {
                throw ValidationException::withMessages([
                    'inventory_audit_item' => 'Temuan ini sudah ditindaklanjuti.',
                ]);
            }

            $action = (string) $data['resolution_action'];
            $notes = (string) $data['resolution_notes'];
            $this->applyResolution($locked, $action, $notes, $actor);
            $locked->forceFill([
                'resolution_action' => $action,
                'resolution_notes' => $notes,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            return $locked->fresh(['resolver', 'asset', 'product']);
        }, 3);
    }

    public function close(InventoryAudit $audit, User $actor): InventoryAudit
    {
        return DB::transaction(function () use ($audit, $actor): InventoryAudit {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Stock opname harus disetujui sebelum ditutup.',
                ]);
            }

            $unresolved = $locked->items()
                ->whereIn('finding_status', self::FINDINGS_REQUIRING_RESOLUTION)
                ->whereNull('resolved_at')
                ->count();
            if ($unresolved > 0) {
                throw ValidationException::withMessages([
                    'inventory_audit' => "Masih ada {$unresolved} temuan yang belum ditindaklanjuti.",
                ]);
            }

            $locked->forceFill([
                'status' => 'closed',
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    public function cancel(
        InventoryAudit $audit,
        string $reason,
        User $actor,
    ): InventoryAudit {
        return DB::transaction(function () use ($audit, $reason, $actor): InventoryAudit {
            $locked = InventoryAudit::query()->lockForUpdate()->findOrFail($audit->id);
            $this->guardAudit($locked, $actor);

            if (! in_array($locked->status, ['draft', 'in_progress', 'submitted'], true)) {
                throw ValidationException::withMessages([
                    'inventory_audit' => 'Stock opname yang sudah disetujui tidak dapat dibatalkan.',
                ]);
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    private function snapshotSerializedAssets(InventoryAudit $audit): void
    {
        Asset::query()
            ->where('current_branch_id', $audit->branch_id)
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('company_id', $audit->company_id)
                ->where('tracking_type', 'serialized'))
            ->orderBy('id')
            ->chunkById(200, function ($assets) use ($audit): void {
                foreach ($assets as $asset) {
                    InventoryAuditItem::query()->create([
                        'inventory_audit_id' => $audit->id,
                        'product_id' => $asset->product_id,
                        'asset_id' => $asset->id,
                        'expected_branch_id' => $asset->current_branch_id,
                        'tracking_type' => 'serialized',
                        'expected_status' => $asset->status,
                        'expected_condition' => $asset->condition,
                        'expected_quantity' => in_array(
                            $asset->status,
                            self::PHYSICALLY_EXPECTED_STATUSES,
                            true,
                        ) ? 1 : 0,
                        'finding_status' => 'pending',
                    ]);
                }
            });
    }

    private function snapshotQuantityInventory(InventoryAudit $audit): void
    {
        BranchInventory::query()
            ->where('branch_id', $audit->branch_id)
            ->whereHas('product', fn (Builder $query) => $query
                ->where('company_id', $audit->company_id)
                ->where('tracking_type', 'quantity')
                ->where('is_active', true))
            ->orderBy('id')
            ->each(function (BranchInventory $inventory) use ($audit): void {
                InventoryAuditItem::query()->create([
                    'inventory_audit_id' => $audit->id,
                    'product_id' => $inventory->product_id,
                    'asset_id' => null,
                    'expected_branch_id' => $audit->branch_id,
                    'tracking_type' => 'quantity',
                    'expected_quantity' => max(
                        0,
                        $inventory->quantity_on_hand
                            - $inventory->quantity_rented
                            - $inventory->quantity_in_transfer,
                    ),
                    'finding_status' => 'pending',
                ]);
            });
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function finding(
        InventoryAuditItem $item,
        InventoryAudit $audit,
        int $countedQuantity,
        ?string $observedStatus,
        ?string $observedCondition,
    ): array {
        if ($item->tracking_type === 'quantity') {
            if ($countedQuantity === $item->expected_quantity) {
                return ['matched', []];
            }

            return ['discrepancy', ['quantity_mismatch']];
        }

        if ($countedQuantity === 0) {
            return $item->expected_quantity === 0
                ? ['verified_offsite', []]
                : ['missing', ['asset_missing']];
        }

        $issues = [];
        if ($item->expected_branch_id !== $audit->branch_id) {
            $issues[] = 'wrong_branch';
        }
        if ($item->expected_quantity === 0) {
            $issues[] = 'unexpected_presence';
        }
        if ($item->expected_status !== $observedStatus) {
            $issues[] = 'status_mismatch';
        }
        if ($item->expected_condition !== $observedCondition) {
            $issues[] = 'condition_mismatch';
        }

        if ($item->expected_branch_id !== $audit->branch_id) {
            return ['unexpected', $issues];
        }

        return $issues === [] ? ['matched', []] : ['discrepancy', $issues];
    }

    private function applyResolution(
        InventoryAuditItem $item,
        string $action,
        string $notes,
        User $actor,
    ): void {
        if (! in_array($action, $this->allowedResolutionActions($item), true)) {
            throw ValidationException::withMessages([
                'resolution_action' => 'Tindakan resolusi tidak sesuai dengan jenis temuan.',
            ]);
        }

        if (in_array($action, ['accept_no_change', 'transfer_required', 'status_review_required'], true)) {
            return;
        }

        if ($action === 'adjust_quantity') {
            $this->adjustQuantity($item);

            return;
        }

        $asset = $item->asset_id === null
            ? null
            : Asset::query()->lockForUpdate()->findOrFail($item->asset_id);
        if ($asset === null) {
            throw ValidationException::withMessages([
                'resolution_action' => 'Tindakan ini hanya tersedia untuk unit serialized.',
            ]);
        }

        if ($action === 'update_condition') {
            if ($item->observed_condition === null) {
                throw ValidationException::withMessages([
                    'resolution_action' => 'Kondisi aktual belum tercatat.',
                ]);
            }
            $fromCondition = $asset->condition;
            $asset->forceFill(['condition' => $item->observed_condition])->save();
            $this->recordAssetHistory($asset, $item, $asset->status, $fromCondition, $notes, $actor);

            return;
        }

        if ($action === 'create_maintenance') {
            $this->maintenance->create([
                'asset_id' => $asset->id,
                'type' => 'inspection',
                'problem_description' => $notes,
                'vendor_name' => null,
                'estimated_cost' => 0,
            ], $actor);

            return;
        }

        if ($action === 'mark_lost') {
            if (in_array($asset->status, ['rented', 'in_transit'], true)) {
                throw ValidationException::withMessages([
                    'resolution_action' => 'Aset rental atau in transit tidak dapat langsung ditandai hilang.',
                ]);
            }
            if ($asset->maintenanceOrders()->whereIn('status', ['reported', 'in_progress'])->exists()) {
                throw ValidationException::withMessages([
                    'resolution_action' => 'Selesaikan maintenance aktif sebelum menandai aset hilang.',
                ]);
            }

            $fromStatus = $asset->status;
            $asset->forceFill(['status' => 'lost'])->save();
            $this->recordAssetHistory($asset, $item, $fromStatus, $asset->condition, $notes, $actor);
            $this->inventory->syncSerialized($asset->product_id, $asset->current_branch_id);

            return;
        }

        throw ValidationException::withMessages([
            'resolution_action' => 'Tindakan resolusi tidak sesuai dengan jenis item.',
        ]);
    }

    /** @return list<string> */
    private function allowedResolutionActions(InventoryAuditItem $item): array
    {
        if ($item->tracking_type === 'quantity') {
            return ['adjust_quantity', 'accept_no_change'];
        }

        if ($item->finding_status === 'unexpected') {
            return ['transfer_required', 'accept_no_change'];
        }

        if ($item->finding_status === 'missing') {
            return ['mark_lost', 'transfer_required', 'accept_no_change'];
        }

        return [
            'update_condition',
            'create_maintenance',
            'status_review_required',
            'accept_no_change',
        ];
    }

    private function adjustQuantity(InventoryAuditItem $item): void
    {
        if ($item->tracking_type !== 'quantity' || $item->counted_quantity === null) {
            throw ValidationException::withMessages([
                'resolution_action' => 'Penyesuaian jumlah hanya tersedia untuk stok quantity.',
            ]);
        }

        $audit = InventoryAudit::query()->findOrFail($item->inventory_audit_id);
        $inventory = BranchInventory::query()
            ->where('branch_id', $audit->branch_id)
            ->where('product_id', $item->product_id)
            ->lockForUpdate()
            ->firstOrFail();
        $minimumOnSite = $inventory->quantity_reserved + $inventory->quantity_maintenance;
        if ($item->counted_quantity < $minimumOnSite) {
            throw ValidationException::withMessages([
                'resolution_action' => 'Jumlah fisik tidak boleh lebih kecil dari stok reserved dan maintenance aktif.',
            ]);
        }

        $inventory->forceFill([
            'quantity_on_hand' => $item->counted_quantity
                + $inventory->quantity_rented
                + $inventory->quantity_in_transfer,
        ])->save();
    }

    private function recordAssetHistory(
        Asset $asset,
        InventoryAuditItem $item,
        string $fromStatus,
        string $fromCondition,
        string $reason,
        User $actor,
    ): void {
        DB::table('asset_status_histories')->insert([
            'asset_id' => $asset->id,
            'branch_id' => $asset->current_branch_id,
            'from_status' => $fromStatus,
            'to_status' => $asset->status,
            'from_condition' => $fromCondition,
            'to_condition' => $asset->condition,
            'source_type' => InventoryAuditItem::class,
            'source_id' => $item->id,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextNumber(Branch $branch): string
    {
        $prefix = 'STO-'.$branch->code.'-'.now()->format('ymd').'-';
        $last = InventoryAudit::query()
            ->where('branch_id', $branch->id)
            ->where('audit_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('audit_number')
            ->value('audit_number');
        $sequence = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function guardAudit(InventoryAudit $audit, User $actor): void
    {
        if ($audit->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->guardBranch($audit->branch_id, $actor);
    }

    private function guardBranch(int $branchId, User $actor): void
    {
        abort_unless($actor->accessibleBranches()->whereKey($branchId)->exists(), 404);
    }

    /** @return list<UploadedFile> */
    private function uploadedPhotos(mixed $photos): array
    {
        if (! is_array($photos)) {
            return [];
        }

        $uploaded = [];

        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                $uploaded[] = $photo;
            }
        }

        return $uploaded;
    }
}
