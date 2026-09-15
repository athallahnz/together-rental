<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 50);
            $table->string('document_type', 30);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('source_reference', 80);
            $table->unsignedInteger('version')->default(1);
            $table->char('content_hash', 64);
            $table->json('snapshot');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->timestamps();

            $table->unique('document_number');
            $table->unique(
                ['document_type', 'source_type', 'source_id', 'version'],
                'transaction_documents_source_version_unique',
            );
            $table->index(['branch_id', 'document_type', 'issued_at'], 'transaction_documents_branch_type_index');
            $table->index(['source_type', 'source_id'], 'transaction_documents_source_index');
            $table->index('content_hash');
        });

        $now = now();
        foreach ([
            ['documents.view', 'View transaction documents'],
            ['documents.issue', 'Issue transaction documents'],
        ] as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'documents',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $grants = [
            'super-admin' => ['documents.view', 'documents.issue'],
            'owner-management' => ['documents.view'],
            'branch-manager' => ['documents.view', 'documents.issue'],
            'rental-operator' => ['documents.view', 'documents.issue'],
            'cashier' => ['documents.view', 'documents.issue'],
        ];

        foreach ($grants as $roleSlug => $permissionSlugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');
            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $permissionId, 'role_id' => $roleId],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['documents.view', 'documents.issue'])
            ->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('transaction_documents');
    }
};
