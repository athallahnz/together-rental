<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku', 50);
            $table->string('name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('tracking_type', 20)->default('serialized');
            $table->text('description')->nullable();
            $table->decimal('replacement_value', 18, 2)->default(0);
            $table->boolean('is_rentable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('owning_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('current_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('asset_code', 60)->unique();
            $table->string('serial_number', 120)->nullable()->index();
            $table->string('status', 30)->default('available')->index();
            $table->string('condition', 30)->default('good');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 18, 2)->default(0);
            $table->decimal('replacement_value', 18, 2)->default(0);
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['current_branch_id', 'status']);
            $table->index(['product_id', 'current_branch_id']);
        });

        Schema::create('branch_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_on_hand')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_rented')->default(0);
            $table->unsignedInteger('quantity_maintenance')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::create('asset_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('from_condition', 30)->nullable();
            $table->string('to_condition', 30)->nullable();
            $table->nullableMorphs('source', 'asset_status_source_index');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['asset_id', 'changed_at']);
        });

        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('duration_unit', 20);
            $table->unsignedInteger('duration_value')->default(1);
            $table->unsignedInteger('grace_period_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'code'], 'rate_plans_scope_unique');
        });

        Schema::create('product_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->decimal('additional_hour_amount', 18, 2)->default(0);
            $table->decimal('late_fee_amount', 18, 2)->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['product_id', 'branch_id', 'rate_plan_id', 'valid_from'],
                'product_rates_scope_unique'
            );
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_id', 'code'], 'packages_scope_unique');
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_optional')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'product_id']);
        });

        Schema::create('package_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['package_id', 'branch_id', 'rate_plan_id'],
                'package_rates_scope_unique'
            );
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('type', 30);
            $table->decimal('value', 18, 2)->default(0);
            $table->decimal('maximum_discount', 18, 2)->nullable();
            $table->decimal('minimum_transaction', 18, 2)->default(0);
            $table->unsignedInteger('bonus_duration')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'branch_id', 'code'], 'promotions_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('package_rates');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('product_rates');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('asset_status_histories');
        Schema::dropIfExists('branch_inventories');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
