<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(
                ['branch_id', 'status', 'starts_at', 'ends_at'],
                'bookings_public_availability_index',
            );
        });

        Schema::table('booking_items', function (Blueprint $table): void {
            $table->index(
                ['product_id', 'booking_id'],
                'booking_items_product_availability_index',
            );
            $table->index(
                ['package_id', 'booking_id'],
                'booking_items_package_availability_index',
            );
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->index(
                ['branch_id', 'status', 'checked_out_at', 'due_at'],
                'rentals_public_availability_index',
            );
        });

        Schema::table('rental_items', function (Blueprint $table): void {
            $table->index(
                ['product_id', 'rental_id'],
                'rental_items_product_availability_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table): void {
            $table->dropIndex('rental_items_product_availability_index');
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropIndex('rentals_public_availability_index');
        });

        Schema::table('booking_items', function (Blueprint $table): void {
            $table->dropIndex('booking_items_product_availability_index');
            $table->dropIndex('booking_items_package_availability_index');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_public_availability_index');
        });
    }
};
