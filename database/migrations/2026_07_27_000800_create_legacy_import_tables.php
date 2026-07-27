<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_system', 50)->default('RentalV1');
            $table->string('source_filename');
            $table->char('source_sha256', 64)->index();
            $table->unsignedBigInteger('source_size')->default(0);
            $table->string('status', 30)->default('uploaded')->index();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('valid_rows')->default(0);
            $table->unsignedBigInteger('warning_rows')->default(0);
            $table->unsignedBigInteger('error_rows')->default(0);
            $table->unsignedBigInteger('imported_rows')->default(0);
            $table->unsignedBigInteger('skipped_rows')->default(0);
            $table->json('options')->nullable();
            $table->json('summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->dateTime('previewed_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('mapped_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_sha256']);
            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('legacy_import_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->string('source_table', 100);
            $table->string('target_table', 100)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('expected_rows')->default(0);
            $table->unsignedBigInteger('parsed_rows')->default(0);
            $table->unsignedBigInteger('valid_rows')->default(0);
            $table->unsignedBigInteger('warning_rows')->default(0);
            $table->unsignedBigInteger('error_rows')->default(0);
            $table->unsignedBigInteger('imported_rows')->default(0);
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->unique(['batch_id', 'source_table']);
        });

        Schema::create('legacy_import_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->string('source_table', 100);
            $table->string('legacy_key', 191);
            $table->unsignedBigInteger('row_number')->nullable();
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('issue_count')->default(0);
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->dateTime('imported_at')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->unique(
                ['batch_id', 'source_table', 'legacy_key'],
                'legacy_import_rows_source_unique'
            );
            $table->index(['batch_id', 'source_table', 'status'], 'legacy_rows_status_index');
        });

        Schema::create('legacy_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->string('mapping_type', 60);
            $table->string('source_value', 191);
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('transform_rule')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->unique(
                ['batch_id', 'mapping_type', 'source_value'],
                'legacy_import_mappings_source_unique'
            );
        });

        Schema::create('legacy_id_maps', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('source_system', 50);
            $table->string('source_table', 100);
            $table->string('legacy_id', 191);
            $table->string('target_table', 100);
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->unique(
                ['source_system', 'source_table', 'legacy_id'],
                'legacy_id_maps_source_unique'
            );
            $table->index(['target_table', 'target_id']);
        });

        Schema::create('legacy_import_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('legacy_import_row_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('severity', 20)->default('error')->index();
            $table->string('code', 80)->index();
            $table->string('field', 100)->nullable();
            $table->text('message');
            $table->text('original_value')->nullable();
            $table->json('suggested_resolution')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->index(['batch_id', 'severity', 'status'], 'legacy_issues_queue_index');
        });

        Schema::create('legacy_import_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('batch_id')->references('id')->on('legacy_import_batches')->cascadeOnDelete();
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_events');
        Schema::dropIfExists('legacy_import_issues');
        Schema::dropIfExists('legacy_id_maps');
        Schema::dropIfExists('legacy_import_mappings');
        Schema::dropIfExists('legacy_import_rows');
        Schema::dropIfExists('legacy_import_tables');
        Schema::dropIfExists('legacy_import_batches');
    }
};
