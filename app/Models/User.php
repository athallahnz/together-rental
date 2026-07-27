<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $current_branch_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $status
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_logout_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'current_branch_id', 'name', 'email', 'password', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['branch_id', 'assigned_by', 'assigned_at', 'expires_at']);
    }

    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function hasPermission(string $permission): bool
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.user_id', $this->id)
            ->where('permissions.slug', $permission)
            ->where(function ($query): void {
                $query
                    ->whereNull('roles.company_id')
                    ->orWhere('roles.company_id', $this->company_id);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            })
            ->where(function ($query): void {
                $query
                    ->where('roles.scope', 'company')
                    ->orWhereNull('role_user.branch_id')
                    ->orWhere('role_user.branch_id', $this->current_branch_id);
            })
            ->exists();
    }

    public function hasCompanyScopedRole(): bool
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->id)
            ->where('roles.scope', 'company')
            ->where(function ($query): void {
                $query
                    ->whereNull('roles.company_id')
                    ->orWhere('roles.company_id', $this->company_id);
            })
            ->where(function ($query): void {
                $query
                    ->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            })
            ->exists();
    }

    /** @return Builder<Branch> */
    public function accessibleBranches(): Builder
    {
        $query = Branch::query()
            ->where('company_id', $this->company_id)
            ->where('is_active', true);

        if ($this->company_id === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasCompanyScopedRole()) {
            return $query;
        }

        $switchAssignments = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('user_id', $this->id)
            ->where('permissions.slug', 'branches.switch')
            ->where(function ($roleQuery): void {
                $roleQuery
                    ->whereNull('roles.company_id')
                    ->orWhere('roles.company_id', $this->company_id);
            })
            ->where(function ($roleQuery): void {
                $roleQuery
                    ->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            });

        $branchUserQuery = DB::table('branch_user')
            ->where('user_id', $this->id)
            ->where('is_active', true);

        if ((clone $switchAssignments)->whereNull('role_user.branch_id')->exists()) {
            return $query->whereIn('id', $branchUserQuery->pluck('branch_id'));
        }

        $assignedBranchIds = $switchAssignments
            ->whereNotNull('role_user.branch_id')
            ->pluck('role_user.branch_id');

        $branchIds = $branchUserQuery
            ->whereIn('branch_id', $assignedBranchIds)
            ->pluck('branch_id');

        return $query->whereIn('id', $branchIds);
    }

    public function canAccessBranch(Branch $branch): bool
    {
        return $this->accessibleBranches()
            ->whereKey($branch->getKey())
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'current_branch_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
