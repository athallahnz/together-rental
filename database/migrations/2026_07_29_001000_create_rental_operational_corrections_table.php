<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_operational_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_return_id')->constrained('rental_returns')->restrictOnDelete();
            $table->foreignId('replacement_return_id')->nullable()
                ->constrained('rental_returns')->nullOnDelete();
            $table->string('correction_number', 60);
            $table->string('status', 30)->default('open')->index();
            $table->text('reason');
            $table->json('snapshot_before');
            $table->json('snapshot_after')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'correction_number'],
                'roc_branch_correction_number_uq',
            );
            $table->index(['rental_id', 'status']);
            $table->index(['original_return_id', 'status']);
        });

        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'rentals.reopen_return',
            'name' => 'Reopen completed rental return',
            'module' => 'rentals',
            'description' => 'Reopen a completed return for controlled operational correction.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superAdminRoleIds = DB::table('roles')->where('slug', 'super-admin')->pluck('id');

        foreach ($superAdminRoleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('slug', 'rentals.reopen_return')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('rental_operational_corrections');
    }
};
