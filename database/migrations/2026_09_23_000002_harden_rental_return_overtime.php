<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_items', function (Blueprint $table): void {
            $table->json('overtime_snapshot')->nullable()->after('due_at');
        });

        Schema::table('rental_returns', function (Blueprint $table): void {
            $table->json('overtime_breakdown')->nullable()->after('late_fee_amount');
        });

        Schema::table('rental_return_items', function (Blueprint $table): void {
            $table->json('overtime_breakdown')->nullable()->after('late_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('rental_return_items', function (Blueprint $table): void {
            $table->dropColumn('overtime_breakdown');
        });

        Schema::table('rental_returns', function (Blueprint $table): void {
            $table->dropColumn('overtime_breakdown');
        });

        Schema::table('rental_items', function (Blueprint $table): void {
            $table->dropColumn('overtime_snapshot');
        });
    }
};
