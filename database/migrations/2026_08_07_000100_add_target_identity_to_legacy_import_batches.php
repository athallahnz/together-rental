<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_import_batches', function (Blueprint $table): void {
            $table->string('source_city', 100)->nullable()->after('branch_id');
            $table->char('import_prefix', 3)->nullable()->after('source_city');
            $table->index(['branch_id', 'import_prefix'], 'legacy_import_batches_branch_prefix_index');
            $table->index('import_prefix', 'legacy_import_batches_prefix_index');
        });

        DB::table('legacy_import_batches')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'branch_id', 'options'])
            ->each(function (object $batch): void {
                $branch = DB::table('branches')
                    ->where('id', $batch->branch_id)
                    ->first(['code', 'city']);

                if ($branch === null) {
                    return;
                }

                $options = is_string($batch->options)
                    ? json_decode($batch->options, true)
                    : (array) ($batch->options ?? []);
                $candidate = strtoupper(trim((string) ($options['import_prefix'] ?? $options['branch_code'] ?? $branch->code)));
                $lettersOnly = preg_replace('/[^A-Z]/', '', Str::ascii($candidate)) ?? '';
                $prefix = strlen($lettersOnly) >= 3 ? substr($lettersOnly, 0, 3) : null;

                DB::table('legacy_import_batches')
                    ->where('id', $batch->id)
                    ->update([
                        'source_city' => $branch->city,
                        'import_prefix' => $prefix,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('legacy_import_batches', function (Blueprint $table): void {
            $table->dropIndex('legacy_import_batches_branch_prefix_index');
            $table->dropIndex('legacy_import_batches_prefix_index');
            $table->dropColumn(['source_city', 'import_prefix']);
        });
    }
};
