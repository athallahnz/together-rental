<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('short_description_en', 320)->nullable();
            $table->text('description_en')->nullable();
            $table->string('seo_title_en', 180)->nullable();
            $table->string('seo_description_en', 320)->nullable();
        });
        Schema::table('packages', function (Blueprint $table): void {
            $table->text('description_en')->nullable();
            $table->string('seo_title_en', 180)->nullable();
            $table->string('seo_description_en', 320)->nullable();
        });
    }

    public function down(): void
    {
        // Explicit rollback discards only newly added translations. Keep old content.
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'short_description_en', 'description_en', 'seo_title_en', 'seo_description_en',
        ]));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn([
            'description_en', 'seo_title_en', 'seo_description_en',
        ]));
    }
};
