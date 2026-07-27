<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('normalized_name');
            $table->unsignedInteger('sort_order')->default(0)->after('logo_path')->index();
        });
    }

    public function down(): void
    {
        Schema::table('catalog_brands', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'sort_order']);
        });
    }
};
