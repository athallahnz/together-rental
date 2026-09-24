<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_collaterals', function (Blueprint $table): void {
            $table->foreignId('customer_identity_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_identities')
                ->nullOnDelete();
            $table->string('source_type', 30)->default('manual')->after('customer_identity_id');
            $table->json('identity_snapshot')->nullable()->after('holder_name');

            $table->index(['customer_identity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('rental_collaterals', function (Blueprint $table): void {
            $table->dropIndex(['customer_identity_id', 'status']);
            $table->dropForeign(['customer_identity_id']);
            $table->dropColumn(['customer_identity_id', 'source_type', 'identity_snapshot']);
        });
    }
};
