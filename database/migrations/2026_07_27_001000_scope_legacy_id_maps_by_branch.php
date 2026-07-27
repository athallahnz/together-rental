<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_id_maps', function (Blueprint $table) {
            $table->dropUnique('legacy_id_maps_source_unique');
            $table->unique(
                ['branch_id', 'source_system', 'source_table', 'legacy_id'],
                'legacy_id_maps_branch_source_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('legacy_id_maps', function (Blueprint $table) {
            $table->dropUnique('legacy_id_maps_branch_source_unique');
            $table->unique(
                ['source_system', 'source_table', 'legacy_id'],
                'legacy_id_maps_source_unique',
            );
        });
    }
};
