<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->unique(
                ['cash_session_id', 'payment_id', 'type'],
                'cash_transactions_payment_type_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropUnique('cash_transactions_payment_type_unique');
        });
    }
};
