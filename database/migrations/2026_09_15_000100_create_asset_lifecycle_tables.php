<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_acquisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('acquisition_number', 60)->unique();
            $table->date('acquisition_date')->index();
            $table->string('vendor_name', 150)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'acquisition_date'], 'asset_acquisitions_scope_index');
        });

        Schema::create('asset_acquisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_acquisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('purchase_price', 18, 2)->default(0);
            $table->decimal('replacement_value', 18, 2)->default(0);
            $table->string('serial_number', 120)->nullable();
            $table->date('warranty_until')->nullable();
            $table->timestamps();

            $table->index(['asset_acquisition_id', 'product_id'], 'asset_acquisition_items_product_index');
        });

        Schema::create('asset_disposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->string('disposal_number', 60)->unique();
            $table->date('disposal_date')->index();
            $table->string('method', 30);
            $table->decimal('sale_amount', 18, 2)->default(0);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'branch_id', 'disposal_date'], 'asset_disposals_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
        Schema::dropIfExists('asset_acquisition_items');
        Schema::dropIfExists('asset_acquisitions');
    }
};
