<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapAdministrator extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rental:bootstrap-admin
                            {email : Existing user email address}
                            {--company=TK : Company code}
                            {--branch= : Branch code; defaults to INITIAL_BRANCH_CODE}';

    /**
     * @var string
     */
    protected $description = 'Assign an existing user as the initial Together Rental super administrator';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $companyCode = strtoupper(trim((string) $this->option('company')));
        $branchCode = strtoupper(trim(
            (string) ($this->option('branch') ?: config('rental.initial_branch_code', 'PNG')),
        ));

        if (strcasecmp($email, 'EMAIL_LOGIN_ANDA') === 0) {
            $this->components->error('Replace EMAIL_LOGIN_ANDA with the email address of an existing user.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("User with email [{$email}] was not found.");

            return self::FAILURE;
        }

        $company = DB::table('companies')->where('code', $companyCode)->first(['id', 'name']);

        if ($company === null) {
            $this->components->error("Company [{$companyCode}] was not found. Run `php artisan db:seed` first.");

            return self::FAILURE;
        }

        $branch = DB::table('branches')
            ->where('company_id', $company->id)
            ->where('code', $branchCode)
            ->first(['id', 'name']);

        if ($branch === null) {
            $this->components->error("Branch [{$branchCode}] was not found for company [{$companyCode}].");

            return self::FAILURE;
        }

        $roleId = DB::table('roles')
            ->where('company_id', $company->id)
            ->where('slug', 'super-admin')
            ->value('id');

        if ($roleId === null) {
            $this->components->error('The super-admin role was not found. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $company, $branch, $roleId): void {
            $now = now();

            $user->forceFill([
                'company_id' => $company->id,
                'current_branch_id' => $branch->id,
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? $now,
            ])->save();

            DB::table('branch_user')->updateOrInsert(
                ['branch_id' => $branch->id, 'user_id' => $user->id],
                [
                    'is_default' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('role_user')->updateOrInsert(
                ['role_id' => $roleId, 'user_id' => $user->id, 'branch_id' => null],
                [
                    'assigned_by' => $user->id,
                    'assigned_at' => $now,
                    'expires_at' => null,
                ],
            );
        });

        $this->components->info("{$user->email} is now Super Admin for {$company->name} with default branch {$branch->name}.");

        return self::SUCCESS;
    }
}
