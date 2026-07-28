<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->index(
                ['current_branch_id', 'purchase_date', 'is_active'],
                'assets_analytics_scope_index',
            );
        });

        Schema::table('rental_item_assets', function (Blueprint $table): void {
            $table->index(
                ['asset_id', 'checked_out_at', 'returned_at'],
                'rental_asset_utilization_index',
            );
        });

        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->index(
                ['status', 'approved_at'],
                'rental_extensions_analytics_index',
            );
        });

        Schema::table('maintenance_orders', function (Blueprint $table): void {
            $table->index(
                ['status', 'completed_at'],
                'maintenance_completed_analytics_index',
            );
            $table->index(
                ['asset_id', 'started_at', 'completed_at'],
                'maintenance_downtime_analytics_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table): void {
            $table->dropIndex('maintenance_completed_analytics_index');
            $table->dropIndex('maintenance_downtime_analytics_index');
        });

        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->dropIndex('rental_extensions_analytics_index');
        });

        Schema::table('rental_item_assets', function (Blueprint $table): void {
            $table->dropIndex('rental_asset_utilization_index');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('assets_analytics_scope_index');
        });
    }
};
