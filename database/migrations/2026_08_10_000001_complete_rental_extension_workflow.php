<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('rental_extension_id')
                ->nullable()
                ->after('rental_id')
                ->constrained('rental_extensions')
                ->nullOnDelete();
            $table->index(['rental_extension_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['rental_extension_id', 'status']);
            $table->dropConstrainedForeignId('rental_extension_id');
        });
    }
};
