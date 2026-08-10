<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('audit_number', 60);
            $table->string('title', 150);
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('snapshot_item_count')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'audit_number']);
            $table->index(['branch_id', 'status', 'scheduled_at'], 'inventory_audits_branch_status_index');
        });

        Schema::create('inventory_audit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expected_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('tracking_type', 20);
            $table->string('expected_status', 30)->nullable();
            $table->string('expected_condition', 30)->nullable();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->string('finding_status', 30)->default('pending')->index();
            $table->json('issue_flags')->nullable();
            $table->string('observed_status', 30)->nullable();
            $table->string('observed_condition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('resolution_action', 40)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('counted_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['inventory_audit_id', 'asset_id'], 'inventory_audit_asset_unique');
            $table->index(['inventory_audit_id', 'product_id'], 'inventory_audit_product_index');
        });

        Schema::create('inventory_audit_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_audit_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('photo');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sha256', 64);
            $table->string('capture_source', 20)->default('camera');
            $table->dateTime('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inventory_audit_item_id', 'captured_at'], 'inventory_audit_media_item_index');
        });

        $permissions = [
            ['inventory-audits.view', 'View stock opname and inventory audits'],
            ['inventory-audits.create', 'Create stock opname schedules'],
            ['inventory-audits.count', 'Record physical inventory counts'],
            ['inventory-audits.approve', 'Approve inventory audit results'],
            ['inventory-audits.resolve', 'Resolve inventory audit findings'],
            ['inventory-audits.cancel', 'Cancel inventory audits'],
        ];
        $now = now();

        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'inventory-audits',
                    'description' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $rolePermissions = [
            'super-admin' => array_column($permissions, 0),
            'owner-management' => [
                'inventory-audits.view',
                'inventory-audits.approve',
                'inventory-audits.resolve',
            ],
            'branch-manager' => array_column($permissions, 0),
            'inventory-staff' => [
                'inventory-audits.view',
                'inventory-audits.create',
                'inventory-audits.count',
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $slugs) {
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
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', [
                'inventory-audits.view',
                'inventory-audits.create',
                'inventory-audits.count',
                'inventory-audits.approve',
                'inventory-audits.resolve',
                'inventory-audits.cancel',
            ])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('inventory_audit_media');
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
    }
};
