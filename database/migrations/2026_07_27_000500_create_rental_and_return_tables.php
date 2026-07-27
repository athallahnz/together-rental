<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('guarantor_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('checked_out_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('checked_in_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rental_number', 50);
            $table->string('legacy_number', 50)->nullable()->index();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('checked_out_at')->nullable();
            $table->dateTime('due_at');
            $table->dateTime('returned_at')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('booking_payment_amount', 18, 2)->default(0);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->decimal('late_fee_amount', 18, 2)->default(0);
            $table->decimal('damage_fee_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'rental_number']);
            $table->index(['branch_id', 'status', 'due_at']);
            $table->index(['customer_id', 'checked_out_at']);
        });

        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('description', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('unit_rate', 18, 2)->default(0);
            $table->decimal('additional_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->dateTime('due_at')->nullable();
            $table->string('status', 30)->default('out')->index();
            $table->timestamps();

            $table->index(['rental_id', 'product_id']);
        });

        Schema::create('rental_item_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('checkout_condition', 30)->default('good');
            $table->string('return_condition', 30)->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('status', 30)->default('out')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rental_item_id', 'asset_id']);
            $table->index(['asset_id', 'status']);
        });

        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('extension_number', 50);
            $table->string('legacy_number', 50)->nullable()->index();
            $table->dateTime('previous_due_at');
            $table->dateTime('extended_due_at');
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'extension_number']);
            $table->index(['rental_id', 'extended_due_at']);
        });

        Schema::create('rental_extension_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_extension_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->dateTime('previous_due_at')->nullable();
            $table->dateTime('extended_due_at');
            $table->decimal('unit_rate', 18, 2)->default(0);
            $table->decimal('additional_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['rental_extension_id', 'rental_item_id'], 'extension_items_unique');
        });

        Schema::create('rental_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('return_number', 50);
            $table->string('type', 20)->default('final');
            $table->string('status', 30)->default('completed')->index();
            $table->dateTime('returned_at');
            $table->decimal('late_fee_amount', 18, 2)->default(0);
            $table->decimal('damage_fee_amount', 18, 2)->default(0);
            $table->decimal('cleaning_fee_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_charge_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'return_number']);
            $table->index(['rental_id', 'returned_at']);
        });

        Schema::create('rental_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition', 30)->default('good');
            $table->string('status', 30)->default('returned');
            $table->decimal('late_fee_amount', 18, 2)->default(0);
            $table->decimal('damage_fee_amount', 18, 2)->default(0);
            $table->decimal('cleaning_fee_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rental_return_id', 'rental_item_id'], 'return_items_parent_index');
        });

        Schema::create('rental_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('number', 100);
            $table->string('holder_name', 150)->nullable();
            $table->string('status', 30)->default('held')->index();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
        });

        Schema::create('asset_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('rental_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_return_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('condition', 30);
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inspected_at');
            $table->timestamps();

            $table->index(['asset_id', 'inspected_at']);
        });

        Schema::create('asset_inspection_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_inspection_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('photo');
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('damage_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_return_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->text('description');
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('charged')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('maintenance_number', 50);
            $table->string('type', 30);
            $table->string('status', 30)->default('reported')->index();
            $table->text('problem_description');
            $table->text('resolution')->nullable();
            $table->string('vendor_name', 150)->nullable();
            $table->decimal('estimated_cost', 18, 2)->default(0);
            $table->decimal('actual_cost', 18, 2)->default(0);
            $table->dateTime('reported_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'maintenance_number']);
            $table->index(['asset_id', 'status']);
        });

        Schema::create('rental_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['rental_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_status_histories');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('damage_charges');
        Schema::dropIfExists('asset_inspection_media');
        Schema::dropIfExists('asset_inspections');
        Schema::dropIfExists('rental_collaterals');
        Schema::dropIfExists('rental_return_items');
        Schema::dropIfExists('rental_returns');
        Schema::dropIfExists('rental_extension_items');
        Schema::dropIfExists('rental_extensions');
        Schema::dropIfExists('rental_item_assets');
        Schema::dropIfExists('rental_items');
        Schema::dropIfExists('rentals');
    }
};
