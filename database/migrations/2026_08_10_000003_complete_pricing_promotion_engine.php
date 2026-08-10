<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('pricing_snapshot')->nullable()->after('deposit_paid');
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->json('pricing_snapshot')->nullable()->after('damage_fee_amount');
        });

        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->foreignId('promotion_id')
                ->nullable()
                ->after('rental_id')
                ->constrained('promotions')
                ->nullOnDelete();
            $table->json('pricing_snapshot')->nullable()->after('paid_amount');
            $table->index(['promotion_id', 'status']);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->index(['company_id', 'is_active', 'starts_at', 'ends_at'], 'promotions_active_period_index');
            $table->index(['branch_id', 'is_active'], 'promotions_branch_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropIndex('promotions_active_period_index');
            $table->dropIndex('promotions_branch_active_index');
        });

        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->dropIndex(['promotion_id', 'status']);
            $table->dropColumn('pricing_snapshot');
            $table->dropConstrainedForeignId('promotion_id');
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropColumn('pricing_snapshot');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('pricing_snapshot');
        });
    }
};
