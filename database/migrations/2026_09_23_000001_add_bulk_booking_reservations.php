<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('reserved');
            $table->dateTime('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason')->nullable();
            $table->timestamps();
            $table->unique(['booking_item_id', 'product_id']);
            $table->index(['branch_id', 'product_id', 'status', 'starts_at', 'ends_at'], 'bulk_reservations_period_index');
        });
        Schema::table('booking_items', function (Blueprint $table) {
            $table->json('stock_requirements')->nullable();
        });
        Schema::table('rental_items', function (Blueprint $table) {
            $table->boolean('is_bulk')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('rental_items', function (Blueprint $table) {
            $table->dropIndex(['is_bulk']);
            $table->dropColumn('is_bulk');
        });
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn('stock_requirements');
        });
        Schema::dropIfExists('bulk_reservations');
    }
};
