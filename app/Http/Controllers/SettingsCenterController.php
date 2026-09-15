<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\TransferSettings;
use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsCenterController extends Controller
{
    /** @var list<string> */
    private const ACCESS_PERMISSIONS = [
        'company.view',
        'company.manage',
        'branches.manage',
        'transfers.settings',
        'notifications.manage',
        'roles.view',
    ];

    public function index(Request $request, TransferSettings $transferSettings): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->company_id !== null, 403);

        $permissions = $this->permissions($actor);
        abort_unless($this->canAccess($permissions), 403);

        $company = Company::query()
            ->whereKey($actor->company_id)
            ->firstOrFail();
        $branches = $this->branches($actor);
        $selectedBranchId = $request->integer('branch_id')
            ?: (int) ($actor->current_branch_id ?? 0)
            ?: (int) ($branches->first()->id ?? 0);

        if ($selectedBranchId !== 0) {
            abort_unless($branches->contains('id', $selectedBranchId), 404);
        }

        /** @var Branch|null $selectedBranch */
        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);

        return Inertia::render('settings/center', [
            'company' => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'legal_name' => $permissions['company_view'] ? $company->legal_name : null,
                'tax_number' => $permissions['company_view'] ? $company->tax_number : null,
                'phone' => $permissions['company_view'] ? $company->phone : null,
                'email' => $permissions['company_view'] ? $company->email : null,
                'address' => $permissions['company_view'] ? $company->address : null,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
                'is_active' => (bool) $company->is_active,
            ],
            'branches' => $branches->map(static fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'city' => $branch->city,
                'timezone' => (string) $branch->timezone,
                'is_active' => (bool) $branch->is_active,
            ])->values(),
            'selectedBranchId' => $selectedBranch?->id,
            'selectedBranch' => $selectedBranch === null ? null : [
                'id' => (int) $selectedBranch->id,
                'code' => (string) $selectedBranch->code,
                'name' => (string) $selectedBranch->name,
                'city' => $selectedBranch->city,
                'timezone' => (string) $selectedBranch->timezone,
                'is_active' => (bool) $selectedBranch->is_active,
            ],
            'publicProfile' => $selectedBranch === null
                ? null
                : $this->publicProfileSummary($selectedBranch),
            'transferPolicy' => $selectedBranch === null || ! $permissions['transfers_settings']
                ? null
                : $transferSettings->all((int) $selectedBranch->id),
            'notifications' => $permissions['notifications_view']
                ? $this->notificationSummary((int) $company->id)
                : null,
            'permissions' => $permissions,
        ]);
    }

    public function updateCompany(
        UpdateCompanySettingsRequest $request,
        ActivityRecorder $activityRecorder,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->company_id !== null, 403);

        $company = Company::query()->whereKey($actor->company_id)->firstOrFail();
        $oldValues = $this->companyAuditValues($company);
        $company->update($request->validated());
        $fresh = $company->fresh();

        if ($fresh instanceof Company) {
            $activityRecorder->record(
                $request,
                'company.settings.updated',
                $fresh,
                $oldValues,
                $this->companyAuditValues($fresh),
            );
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Identitas dan regional perusahaan berhasil diperbarui.',
        ]);
    }

    /** @return array<string, bool> */
    private function permissions(User $actor): array
    {
        return [
            'company_view' => $actor->can('company.view') || $actor->can('company.manage'),
            'company_manage' => $actor->can('company.manage'),
            'branches_view' => $actor->can('branches.view') || $actor->can('branches.manage'),
            'branches_manage' => $actor->can('branches.manage'),
            'transfers_settings' => $actor->can('transfers.settings'),
            'notifications_view' => $actor->can('notifications.view') || $actor->can('notifications.manage'),
            'notifications_manage' => $actor->can('notifications.manage'),
            'roles_view' => $actor->can('roles.view'),
            'audit_view' => $actor->can('audit.view'),
        ];
    }

    /** @param array<string, bool> $permissions */
    private function canAccess(array $permissions): bool
    {
        foreach (self::ACCESS_PERMISSIONS as $permission) {
            $key = str_replace('.', '_', $permission);

            if (($permissions[$key] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Branch> */
    private function branches(User $actor): Collection
    {
        if ($actor->company_id === null) {
            return collect();
        }

        if ($actor->hasCompanyScopedRole()) {
            return Branch::query()
                ->where('company_id', $actor->company_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'city', 'timezone', 'is_active']);
        }

        return $actor->accessibleBranches()
            ->orderBy('name')
            ->get(['branches.id', 'branches.company_id', 'branches.code', 'branches.name', 'branches.city', 'branches.timezone', 'branches.is_active']);
    }

    /** @return array{catalog_enabled: bool, configured_fields: int, total_fields: int} */
    private function publicProfileSummary(Branch $branch): array
    {
        $keys = [
            'public_whatsapp',
            'public_short_address',
            'public_maps_url',
            'public_instagram',
            'public_opening_hours',
            'public_logo_path',
            'public_hero_title',
            'public_hero_description',
        ];
        $rows = DB::table('branch_settings')
            ->where('branch_id', $branch->id)
            ->whereIn('key', ['public_catalog_enabled', ...$keys])
            ->pluck('value', 'key');

        $configured = collect($keys)
            ->filter(function (string $key) use ($rows): bool {
                $decoded = $this->decode($rows->get($key));

                return $decoded !== null && trim((string) $decoded) !== '';
            })
            ->count();

        return [
            'catalog_enabled' => $this->boolean($this->decode($rows->get('public_catalog_enabled'))),
            'configured_fields' => $configured,
            'total_fields' => count($keys),
        ];
    }

    /** @return array{total_rules: int, enabled_rules: int, critical_rules: int} */
    private function notificationSummary(int $companyId): array
    {
        $query = NotificationRule::query()->where('company_id', $companyId);

        return [
            'total_rules' => (clone $query)->count(),
            'enabled_rules' => (clone $query)->where('is_enabled', true)->count(),
            'critical_rules' => (clone $query)
                ->where('is_enabled', true)
                ->where('severity', 'critical')
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function companyAuditValues(Company $company): array
    {
        return $company->only([
            'name',
            'legal_name',
            'tax_number',
            'phone',
            'email',
            'address',
            'timezone',
        ]);
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
