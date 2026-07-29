<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_number', 50);
            $table->string('component', 30);
            $table->string('direction', 20);
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'adjustment_number']);
            $table->index(['rental_id', 'created_at']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'rentals.correct_completed'],
            [
                'name' => 'Correct completed rental financials',
                'module' => 'rentals',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $permissionId = DB::table('permissions')
            ->where('slug', 'rentals.correct_completed')
            ->value('id');
        $roleIds = DB::table('roles')
            ->where('slug', 'super-admin')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_financial_adjustments');

        $permissionId = DB::table('permissions')
            ->where('slug', 'rentals.correct_completed')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
