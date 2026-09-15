<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('expense_number', 50);
            $table->string('status', 20)->default('recorded')->index();
            $table->decimal('amount', 18, 2);
            $table->dateTime('incurred_at');
            $table->string('vendor_name', 160)->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'expense_number']);
            $table->index(['branch_id', 'incurred_at']);
            $table->index(['financial_category_id', 'status']);
        });

        $now = now();
        $permissions = [
            ['expenses.view', 'View operational expenses', 'finance'],
            ['expenses.manage', 'Create and update operational expenses', 'finance'],
            ['expenses.pay', 'Pay operational expenses', 'finance'],
            ['expenses.void', 'Void operational expenses', 'finance'],
        ];

        foreach ($permissions as [$slug, $name, $module]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => $module,
                    'description' => $name.'.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $defaults = [
            'owner-management' => ['expenses.view'],
            'branch-manager' => ['expenses.view', 'expenses.manage', 'expenses.pay', 'expenses.void'],
            'cashier' => ['expenses.view', 'expenses.manage', 'expenses.pay'],
        ];

        foreach ($defaults as $roleSlug => $slugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        [
                            'permission_id' => (int) $permissionId,
                            'role_id' => (int) $roleId,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            }
        }

        $superAdminIds = DB::table('roles')->where('slug', 'super-admin')->pluck('id');
        $allExpensePermissionIds = DB::table('permissions')
            ->whereIn('slug', array_column($permissions, 0))
            ->pluck('id');
        foreach ($superAdminIds as $roleId) {
            foreach ($allExpensePermissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    [
                        'permission_id' => (int) $permissionId,
                        'role_id' => (int) $roleId,
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['expenses.view', 'expenses.manage', 'expenses.pay', 'expenses.void'])
            ->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
