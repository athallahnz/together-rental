<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_transfers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('revision_number')->default(1)->after('status');
            $table->unsignedInteger('lock_version')->default(0)->after('revision_number');
            $table->dateTime('planned_dispatch_at')->nullable()->after('reason');
            $table->dateTime('expected_arrival_at')->nullable()->after('planned_dispatch_at');
            $table->string('shipping_method', 30)->nullable()->after('shipping_notes');
            $table->string('courier_name', 150)->nullable()->after('shipping_method');
            $table->string('courier_phone', 30)->nullable()->after('courier_name');
            $table->string('vehicle_number', 30)->nullable()->after('courier_phone');
            $table->string('tracking_number', 100)->nullable()->after('vehicle_number');
            $table->string('waybill_number', 100)->nullable()->after('tracking_number');
            $table->string('seal_number', 100)->nullable()->after('waybill_number');
            $table->foreignId('last_material_changed_by')->nullable()->after('received_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('last_material_changed_by')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('last_material_changed_at')->nullable()->after('received_at');
            $table->dateTime('cancelled_at')->nullable()->after('last_material_changed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->dateTime('completed_at')->nullable()->after('cancellation_reason');

            $table->index(['company_id', 'status', 'created_at'], 'branch_transfers_company_status_idx');
            $table->index(['from_branch_id', 'status', 'planned_dispatch_at'], 'branch_transfers_origin_status_idx');
            $table->index(['to_branch_id', 'status', 'planned_dispatch_at'], 'branch_transfers_destination_status_idx');
        });

        Schema::table('branch_transfer_items', function (Blueprint $table): void {
            $table->unsignedInteger('line_number')->default(1)->after('branch_transfer_id');
            $table->foreignId('previous_branch_id')->nullable()->after('asset_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('previous_asset_status', 30)->nullable()->after('previous_branch_id');
            $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
            $table->string('receiving_result', 30)->nullable()->after('condition_after');
            $table->string('discrepancy_type', 30)->nullable()->after('receiving_result');
            $table->text('discrepancy_notes')->nullable()->after('discrepancy_type');
            $table->string('resolution_action', 30)->nullable()->after('discrepancy_notes');
            $table->foreignId('resolved_by')->nullable()->after('resolution_action')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable()->after('resolved_by');

            $table->unique(['branch_transfer_id', 'asset_id'], 'branch_transfer_items_asset_unique');
            $table->index(['branch_transfer_id', 'status'], 'branch_transfer_items_status_idx');
            $table->index(['receiving_result', 'resolved_at'], 'branch_transfer_items_receiving_idx');
        });

        Schema::create('branch_transfer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number');
            $table->string('side', 20);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('decision', 20);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at');
            $table->text('notes')->nullable();
            $table->json('payload_snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamps();

            $table->unique(
                ['branch_transfer_id', 'revision_number', 'side'],
                'branch_transfer_approvals_revision_side_unique',
            );
            $table->index(['branch_id', 'decision', 'decided_at'], 'branch_transfer_approvals_branch_idx');
        });

        Schema::create('branch_transfer_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('expense_type', 30);
            $table->string('status', 20)->default('estimated')->index();
            $table->decimal('estimated_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->string('vendor_name', 150)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['branch_transfer_id', 'status'], 'branch_transfer_expenses_status_idx');
        });

        Schema::create('branch_transfer_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_transfer_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_transfer_expense_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('document_type', 30);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('sha256', 64);
            $table->string('capture_source', 20)->default('gallery');
            $table->dateTime('captured_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_transfer_id', 'stage'], 'branch_transfer_documents_stage_idx');
        });

        Schema::table('branch_inventories', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_in_transfer')->default(0)->after('quantity_maintenance');
        });

        Schema::table('asset_inspections', function (Blueprint $table): void {
            $table->foreignId('branch_transfer_item_id')->nullable()->after('rental_return_item_id')
                ->constrained()->nullOnDelete();
            $table->unique(
                ['branch_transfer_item_id', 'type'],
                'asset_inspections_transfer_item_type_unique',
            );
        });

        Schema::table('asset_inspection_media', function (Blueprint $table): void {
            $table->string('capture_source', 20)->default('gallery')->after('caption');
            $table->dateTime('captured_at')->nullable()->after('capture_source');
            $table->foreignId('captured_by')->nullable()->after('captured_at')
                ->constrained('users')->nullOnDelete();
            $table->char('sha256', 64)->nullable()->after('captured_by');
            $table->json('metadata')->nullable()->after('sha256');
        });

        $this->seedTransferFoundation();
    }

    public function down(): void
    {
        $permissionSlugs = [
            'transfers.update',
            'transfers.cancel',
            'transfers.dispatch',
            'transfers.expense',
            'transfers.resolve_discrepancy',
            'transfers.settings',
            'transfers.override',
        ];
        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('asset_inspection_media', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('captured_by');
            $table->dropColumn(['capture_source', 'captured_at', 'sha256', 'metadata']);
        });

        Schema::table('asset_inspections', function (Blueprint $table): void {
            $table->dropUnique('asset_inspections_transfer_item_type_unique');
            $table->dropConstrainedForeignId('branch_transfer_item_id');
        });

        Schema::table('branch_inventories', function (Blueprint $table): void {
            $table->dropColumn('quantity_in_transfer');
        });

        Schema::dropIfExists('branch_transfer_documents');
        Schema::dropIfExists('branch_transfer_expenses');
        Schema::dropIfExists('branch_transfer_approvals');

        Schema::table('branch_transfer_items', function (Blueprint $table): void {
            $table->dropUnique('branch_transfer_items_asset_unique');
            $table->dropIndex('branch_transfer_items_status_idx');
            $table->dropIndex('branch_transfer_items_receiving_idx');
            $table->dropConstrainedForeignId('previous_branch_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn([
                'line_number',
                'previous_asset_status',
                'received_quantity',
                'receiving_result',
                'discrepancy_type',
                'discrepancy_notes',
                'resolution_action',
                'resolved_at',
            ]);
        });

        Schema::table('branch_transfers', function (Blueprint $table): void {
            $table->dropIndex('branch_transfers_company_status_idx');
            $table->dropIndex('branch_transfers_origin_status_idx');
            $table->dropIndex('branch_transfers_destination_status_idx');
            $table->dropConstrainedForeignId('last_material_changed_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'revision_number',
                'lock_version',
                'planned_dispatch_at',
                'expected_arrival_at',
                'shipping_method',
                'courier_name',
                'courier_phone',
                'vehicle_number',
                'tracking_number',
                'waybill_number',
                'seal_number',
                'last_material_changed_at',
                'cancelled_at',
                'cancellation_reason',
                'completed_at',
            ]);
        });
    }

    private function seedTransferFoundation(): void
    {
        $now = now();
        $permissions = [
            ['transfers.update', 'Update branch transfers'],
            ['transfers.cancel', 'Cancel branch transfers'],
            ['transfers.dispatch', 'Dispatch branch transfers'],
            ['transfers.expense', 'Manage branch transfer expenses'],
            ['transfers.resolve_discrepancy', 'Resolve branch transfer discrepancies'],
            ['transfers.settings', 'Manage branch transfer settings'],
            ['transfers.override', 'Override branch transfer guardrails'],
        ];

        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'transfers',
                    'description' => $name.'.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $roleDefaults = [
            'super-admin' => [
                'transfers.update', 'transfers.cancel', 'transfers.dispatch',
                'transfers.expense', 'transfers.resolve_discrepancy',
                'transfers.settings', 'transfers.override',
            ],
            'branch-manager' => [
                'transfers.update', 'transfers.cancel', 'transfers.dispatch',
                'transfers.expense', 'transfers.resolve_discrepancy',
            ],
            'inventory-staff' => ['transfers.dispatch'],
            'cashier' => ['transfers.view', 'transfers.expense'],
        ];

        foreach ($roleDefaults as $roleSlug => $slugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $permissionId],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        }

        // Keputusan bisnis: hanya Manajer Cabang (dan role lain yang diatur
        // eksplisit oleh Super Admin) yang boleh membuat pengajuan transfer.
        $inventoryRoleIds = DB::table('roles')->where('slug', 'inventory-staff')->pluck('id');
        $createPermissionId = DB::table('permissions')->where('slug', 'transfers.create')->value('id');
        if ($createPermissionId !== null) {
            DB::table('permission_role')
                ->whereIn('role_id', $inventoryRoleIds)
                ->where('permission_id', $createPermissionId)
                ->delete();
        }

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            $settings = [
                'transfer_dispatch_capture_mode' => ['string', 'camera_required'],
                'transfer_receiving_capture_mode' => ['string', 'camera_required'],
                'transfer_dispatch_min_photos' => ['integer', 1],
                'transfer_receiving_min_photos' => ['integer', 1],
                'transfer_require_waybill' => ['boolean', true],
                'transfer_allow_gallery_override' => ['boolean', false],
            ];

            foreach ($settings as $key => [$type, $value]) {
                DB::table('branch_settings')->updateOrInsert(
                    ['branch_id' => $branchId, 'key' => $key],
                    [
                        'value_type' => $type,
                        'value' => json_encode($value, JSON_THROW_ON_ERROR),
                        'is_public' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            DB::table('financial_categories')->updateOrInsert(
                ['company_id' => $companyId, 'code' => 'TRANSFER-SHIPPING'],
                [
                    'name' => 'Transfer & Shipping Expense',
                    'type' => 'expense',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
};
