<?php

namespace Tests\Feature\Audit;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AuditTrailCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_management_can_view_filter_and_drill_into_audit_trail(): void
    {
        [$user, $png, $mdn] = $this->companyUser('owner-management');
        $bookingId = 77;
        $this->log($user, $png, 'booking.updated', Booking::class, $bookingId, ['status' => 'draft'], ['status' => 'confirmed'], 'req-booking-1');
        $this->log($user, $mdn, 'transfer.created', null, null, null, ['status' => 'draft'], 'req-transfer-1');

        $this->actingAs($user)
            ->get(route('audit.index', ['module' => 'booking', 'request_id' => 'booking']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('audit/index')
                ->where('summary.total', 1)
                ->has('activities.data', 1)
                ->where('activities.data.0.event', 'booking.updated')
                ->where('activities.data.0.subject.url', "/bookings/{$bookingId}")
                ->where('activities.data.0.changes.0.key', 'status')
                ->where('activities.data.0.request_id', 'req-booking-1'));
    }

    public function test_branch_scoped_auditor_only_sees_assigned_branch(): void
    {
        [$companyUser, $png, $mdn] = $this->companyUser('owner-management');
        $auditor = $this->branchAuditor($companyUser, $png);
        $this->log($companyUser, $png, 'booking.created', null, null, null, ['status' => 'draft']);
        $this->log($companyUser, $mdn, 'booking.created', null, null, null, ['status' => 'draft']);

        $this->actingAs($auditor)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 1)
                ->has('branches', 1)
                ->where('branches.0.id', $png->id));

        $this->actingAs($auditor)
            ->get(route('audit.index', ['branch_id' => $mdn->id]))
            ->assertForbidden();
    }

    public function test_user_without_audit_permission_is_forbidden(): void
    {
        [$companyUser, $png] = $this->companyUser('owner-management');
        $operator = $this->userForRole('rental-operator', $companyUser, $png);

        $this->actingAs($operator)
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_sensitive_values_are_redacted_but_normal_changes_remain_visible(): void
    {
        [$user, $png] = $this->companyUser('owner-management');
        $this->log($user, $png, 'user.updated', User::class, $user->id, [
            'name' => 'Before',
            'password' => 'old-secret',
            'profile' => ['api_token' => 'abc'],
        ], [
            'name' => 'After',
            'password' => 'new-secret',
            'profile' => ['api_token' => 'xyz'],
        ]);

        $this->actingAs($user)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities.data.0.old_values.password', '[REDACTED]')
                ->where('activities.data.0.new_values.password', '[REDACTED]')
                ->where('activities.data.0.old_values.profile.api_token', '[REDACTED]')
                ->where('activities.data.0.new_values.profile.api_token', '[REDACTED]')
                ->where('activities.data.0.changes.0.key', 'name'));
    }

    public function test_date_actor_event_and_search_filters_are_applied_together(): void
    {
        [$user, $png] = $this->companyUser('owner-management');
        $other = User::factory()->create([
            'company_id' => $user->company_id,
            'current_branch_id' => $png->id,
            'name' => 'Audited Actor',
        ]);
        $this->log($other, $png, 'refund.requested', null, null, null, ['reference' => 'RF-SEARCH'], 'REQ-SEARCH', '2026-09-15 10:00:00');
        $this->log($user, $png, 'booking.created', null, null, null, ['reference' => 'BK-OTHER'], 'REQ-OTHER', '2026-09-14 10:00:00');

        $this->actingAs($user)
            ->get(route('audit.index', [
                'actor_id' => $other->id,
                'event' => 'refund.requested',
                'search' => 'Audited Actor',
                'date_from' => '2026-09-15',
                'date_to' => '2026-09-15',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.total', 1)
                ->where('activities.data.0.actor.id', $other->id)
                ->where('activities.data.0.event', 'refund.requested'));
    }

    /** @return array{0: User, 1: Branch, 2: Branch} */
    private function companyUser(string $roleSlug): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $png = Branch::query()->where('company_id', $companyId)->where('code', 'PNG')->firstOrFail();
        $mdn = Branch::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'MDN'],
            [
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
                'province' => 'Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $png->id,
            'status' => 'active',
        ]);
        $this->assignRole($user, $png, $roleSlug, true);

        return [$user, $png, $mdn];
    }

    private function branchAuditor(User $companyUser, Branch $branch): User
    {
        $role = Role::query()->create([
            'company_id' => $companyUser->company_id,
            'name' => 'Branch Auditor',
            'slug' => 'branch-auditor',
            'scope' => 'branch',
            'is_system' => false,
        ]);
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['audit.view', 'branches.switch'])
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);
        $user = User::factory()->create([
            'company_id' => $companyUser->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->assign($user, $branch, $role->id, false);

        return $user;
    }

    private function userForRole(string $roleSlug, User $companyUser, Branch $branch): User
    {
        $user = User::factory()->create([
            'company_id' => $companyUser->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->assignRole($user, $branch, $roleSlug, false);

        return $user;
    }

    private function assignRole(User $user, Branch $branch, string $roleSlug, bool $companyScoped): void
    {
        $roleId = (int) DB::table('roles')
            ->where('company_id', $user->company_id)
            ->where('slug', $roleSlug)
            ->value('id');
        $this->assign($user, $branch, $roleId, $companyScoped);
    }

    private function assign(User $user, Branch $branch, int $roleId, bool $companyScoped): void
    {
        $now = now();
        DB::table('branch_user')->insert([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => $companyScoped ? null : $branch->id,
            'assigned_by' => null,
            'assigned_at' => $now,
            'expires_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function log(
        User $actor,
        Branch $branch,
        string $event,
        ?string $subjectType,
        ?int $subjectId,
        ?array $old,
        ?array $new,
        ?string $requestId = null,
        ?string $createdAt = null,
    ): void {
        DB::table('activity_logs')->insert([
            'company_id' => $actor->company_id,
            'branch_id' => $branch->id,
            'actor_id' => $actor->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'event' => $event,
            'description' => $new['reference'] ?? null,
            'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
            'new_values' => $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'request_id' => $requestId,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
