<?php

use App\Domain\Notifications\NotificationRuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('category', 30)->index();
            $table->string('name', 120);
            $table->text('description');
            $table->string('severity', 20)->default('info');
            $table->string('recipient_permission', 120);
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('lead_minutes')->default(0);
            $table->unsignedInteger('repeat_minutes')->default(1440);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(false);
            $table->string('email_min_severity', 20)->default('critical');
            $table->json('muted_categories')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rule_code', 80)->index();
            $table->string('category', 30)->index();
            $table->string('severity', 20)->index();
            $table->string('title', 180);
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('dedupe_key', 191);
            $table->unsignedInteger('occurrences')->default(1);
            $table->dateTime('first_triggered_at');
            $table->dateTime('last_triggered_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('read_at')->nullable()->index();
            $table->dateTime('dismissed_at')->nullable()->index();
            $table->dateTime('snoozed_until')->nullable()->index();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->string('email_status', 20)->default('not_requested')->index();
            $table->dateTime('email_sent_at')->nullable();
            $table->dateTime('email_failed_at')->nullable();
            $table->text('email_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'resolved_at', 'read_at'], 'notification_inbox_index');
            $table->index(['company_id', 'branch_id', 'rule_code'], 'notification_scope_rule_index');
            $table->index(['source_type', 'source_id'], 'notification_source_index');
        });

        $this->seedPermissions();
        $this->seedRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_messages');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_rules');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $this->permissionSlugs())
            ->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    private function seedPermissions(): void
    {
        $now = now();
        $permissions = [
            ['notifications.view', 'View Notification & Reminder Center'],
            ['notifications.manage', 'Manage notification rules and run reminder scans'],
        ];

        foreach ($permissions as [$slug, $name]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'module' => 'notifications',
                    'description' => $name.'.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $roleDefaults = [
            'super-admin' => ['notifications.view', 'notifications.manage'],
            'owner-management' => ['notifications.view', 'notifications.manage'],
            'branch-manager' => ['notifications.view'],
            'rental-operator' => ['notifications.view'],
            'inventory-staff' => ['notifications.view'],
            'cashier' => ['notifications.view'],
        ];

        foreach ($roleDefaults as $roleSlug => $slugs) {
            $roleIds = DB::table('roles')->where('slug', $roleSlug)->pluck('id');
            $permissionIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => (int) $permissionId, 'role_id' => (int) $roleId],
                        ['created_at' => $now, 'updated_at' => $now],
                    );
                }
            }
        }
    }

    private function seedRules(): void
    {
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach (NotificationRuleCatalog::definitions() as $definition) {
                DB::table('notification_rules')->updateOrInsert(
                    ['company_id' => (int) $companyId, 'code' => $definition['code']],
                    [
                        ...$definition,
                        'is_enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    /** @return list<string> */
    private function permissionSlugs(): array
    {
        return ['notifications.view', 'notifications.manage'];
    }
};
