<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('source_context', 30)->nullable()->after('type');
            $table->index(
                ['branch_id', 'source_context', 'status'],
                'payments_branch_source_status_index',
            );
        });

        DB::table('payments')
            ->where('type', 'transfer_expense')
            ->update(['source_context' => 'transfer_expense']);
        DB::table('payments')
            ->whereNotNull('booking_id')
            ->whereNull('rental_id')
            ->update(['source_context' => 'booking']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_branch_source_status_index');
            $table->dropColumn('source_context');
        });
    }
};
