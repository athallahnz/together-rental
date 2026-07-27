<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('normalized_name', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['company_id', 'normalized_name']);
        });

        Schema::create('catalog_brand_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_brand_id')->constrained('catalog_brands')->cascadeOnDelete();
            $table->string('alias', 100);
            $table->string('normalized_alias', 120)->index();
            $table->timestamps();

            $table->unique(['catalog_brand_id', 'normalized_alias'], 'catalog_brand_alias_unique');
        });

        Schema::create('catalog_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_brand_id')->nullable()->constrained('catalog_brands')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 150);
            $table->json('specifications')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['company_id', 'catalog_brand_id', 'normalized_name'],
                'catalog_models_unique',
            );
        });

        Schema::create('catalog_model_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_model_id')->constrained('catalog_models')->cascadeOnDelete();
            $table->string('alias', 180);
            $table->string('normalized_alias', 200)->index();
            $table->timestamps();

            $table->unique(['catalog_model_id', 'normalized_alias'], 'catalog_model_alias_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('catalog_brand_id')
                ->nullable()
                ->after('brand')
                ->constrained('catalog_brands')
                ->nullOnDelete();
            $table->foreignId('catalog_model_id')
                ->nullable()
                ->after('model')
                ->constrained('catalog_models')
                ->nullOnDelete();
            $table->string('variant', 120)->nullable()->after('catalog_model_id');
            $table->string('enrichment_status', 30)->default('pending')->after('variant')->index();
            $table->dateTime('enriched_at')->nullable()->after('enrichment_status');
        });

        Schema::create('catalog_enrichment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('previewed')->index();
            $table->string('scope', 30)->default('missing');
            $table->boolean('ai_requested')->default(false);
            $table->string('ai_provider', 60)->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('executed_count')->default(0);
            $table->unsignedInteger('verified_count')->default(0);
            $table->unsignedInteger('rollback_skipped_count')->default(0);
            $table->json('options')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('catalog_enrichment_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('catalog_enrichment_runs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_source_name', 180);

            $table->foreignId('suggested_brand_id')
                ->nullable()
                ->constrained('catalog_brands')
                ->nullOnDelete();
            $table->foreignId('suggested_model_id')
                ->nullable()
                ->constrained('catalog_models')
                ->nullOnDelete();
            $table->string('suggested_brand', 100)->nullable();
            $table->string('suggested_model', 120)->nullable();
            $table->string('suggested_variant', 120)->nullable();
            $table->decimal('confidence', 5, 2)->default(0)->index();
            $table->string('source', 30)->default('rule');
            $table->string('status', 30)->default('pending')->index();
            $table->json('reasoning')->nullable();

            $table->foreignId('original_brand_id')->nullable()->constrained('catalog_brands')->nullOnDelete();
            $table->foreignId('original_model_id')->nullable()->constrained('catalog_models')->nullOnDelete();
            $table->string('original_brand', 100)->nullable();
            $table->string('original_model', 120)->nullable();
            $table->string('original_variant', 120)->nullable();
            $table->string('original_enrichment_status', 30)->default('pending');
            $table->dateTime('original_enriched_at')->nullable();

            $table->foreignId('resolved_brand_id')->nullable()->constrained('catalog_brands')->nullOnDelete();
            $table->foreignId('resolved_model_id')->nullable()->constrained('catalog_models')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'product_id']);
            $table->index(['run_id', 'status', 'confidence'], 'catalog_candidates_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_enrichment_candidates');
        Schema::dropIfExists('catalog_enrichment_runs');

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_model_id');
            $table->dropConstrainedForeignId('catalog_brand_id');
            $table->dropIndex(['enrichment_status']);
            $table->dropColumn(['variant', 'enrichment_status', 'enriched_at']);
        });

        Schema::dropIfExists('catalog_model_aliases');
        Schema::dropIfExists('catalog_models');
        Schema::dropIfExists('catalog_brand_aliases');
        Schema::dropIfExists('catalog_brands');
    }
};
