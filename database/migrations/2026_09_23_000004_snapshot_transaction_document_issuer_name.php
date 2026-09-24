<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_documents', function (Blueprint $table): void {
            $table->string('issuer_name_snapshot', 150)->nullable()->after('issued_by');
        });

        DB::table('transaction_documents')
            ->whereNotNull('issued_by')
            ->whereNull('issuer_name_snapshot')
            ->orderBy('id')
            ->chunkById(500, function ($documents): void {
                $userNames = DB::table('users')
                    ->whereIn('id', $documents->pluck('issued_by')->filter()->unique()->values())
                    ->pluck('name', 'id');

                foreach ($documents as $document) {
                    $name = $userNames->get($document->issued_by);
                    if (! is_string($name) || trim($name) === '') {
                        continue;
                    }

                    DB::table('transaction_documents')
                        ->where('id', $document->id)
                        ->update(['issuer_name_snapshot' => $name]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transaction_documents', function (Blueprint $table): void {
            $table->dropColumn('issuer_name_snapshot');
        });
    }
};
