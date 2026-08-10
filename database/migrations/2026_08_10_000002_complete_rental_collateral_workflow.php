<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_collaterals', function (Blueprint $table): void {
            $table->foreignId('received_by')
                ->nullable()
                ->after('received_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('document_original_name')->nullable()->after('document_path');
            $table->string('document_mime_type', 120)->nullable()->after('document_original_name');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('rental_collaterals', function (Blueprint $table): void {
            $table->dropForeign(['received_by']);
            $table->dropColumn([
                'received_by',
                'document_original_name',
                'document_mime_type',
                'document_size',
            ]);
        });
    }
};
