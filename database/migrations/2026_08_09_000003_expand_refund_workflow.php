<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreignId('cash_session_id')->nullable()
                ->after('payment_method_id')
                ->constrained('cash_sessions')
                ->restrictOnDelete();
            $table->string('refund_type', 20)->default('partial')->after('refund_number');
            $table->text('notes')->nullable()->after('reason');
            $table->foreignId('rejected_by')->nullable()
                ->after('approved_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()
                ->after('processed_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->dateTime('cancelled_at')->nullable()->after('processed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->string('proof_path')->nullable()->after('external_reference');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->string('proof_mime_type', 100)->nullable()->after('proof_original_name');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_mime_type');

            $table->index(['branch_id', 'status', 'created_at'], 'refunds_branch_status_created_index');
            $table->index(['payment_id', 'status'], 'refunds_payment_status_index');
        });

        DB::table('refunds')->where('status', 'pending')->update(['status' => 'requested']);

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->foreignId('refund_id')->nullable()
                ->after('payment_id')
                ->constrained('refunds')
                ->restrictOnDelete();
            $table->unique('refund_id', 'cash_transactions_refund_unique');
        });

        $this->seedRefundPermissions();
    }

    public function down(): void
    {
        DB::table('refunds')->where('status', 'requested')->update(['status' => 'pending']);

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $this->permissionSlugs())
            ->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropUnique('cash_transactions_refund_unique');
            $table->dropConstrainedForeignId('refund_id');
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropIndex('refunds_branch_status_created_index');
            $table->dropIndex('refunds_payment_status_index');
            $table->dropConstrainedForeignId('cash_session_id');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'refund_type',
                'notes',
                'rejected_at',
                'rejection_reason',
                'cancelled_at',
                'cancellation_reason',
                'proof_path',
                'proof_original_name',
                'proof_mime_type',
                'proof_size',
            ]);
        });
    }

    private function seedRefundPermissions(): void
    {
        $now = now();
        $permissions = [
            ['refunds.view', 'View refunds'],
            ['refunds.request', 'Request refunds'],
            ['refunds.approve', 'Approve or reject refunds'],
            ['refunds.process', 'Process approved refunds'],
            ['refunds.cancel', 'Cancel refunds'],
        ];

        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'refunds',
                    'description' => $name.'.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $allSlugs = $this->permissionSlugs();
        $legacyRoleIds = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('permissions.slug', 'refunds.manage')
            ->pluck('permission_role.role_id');

        $roleDefaults = [
            'super-admin' => $allSlugs,
            'owner-management' => ['refunds.view', 'refunds.approve', 'refunds.cancel'],
            'branch-manager' => $allSlugs,
            'rental-operator' => ['refunds.view', 'refunds.request'],
            'cashier' => ['refunds.view', 'refunds.request', 'refunds.process'],
        ];

        foreach ($roleDefaults as $roleSlug => $slugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $this->attachPermissions($roleIds, $slugs, $now);
        }

        $this->attachPermissions($legacyRoleIds, $allSlugs, $now);
    }

    /**
     * @param  iterable<int, mixed>  $roleIds
     * @param  list<string>  $slugs
     */
    private function attachPermissions(iterable $roleIds, array $slugs, mixed $now): void
    {
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

    /** @return list<string> */
    private function permissionSlugs(): array
    {
        return [
            'refunds.view',
            'refunds.request',
            'refunds.approve',
            'refunds.process',
            'refunds.cancel',
        ];
    }
};
