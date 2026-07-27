<?php

namespace App\Domain\Catalog;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Validation\Validator;

class CatalogScope
{
    public function validate(
        User $user,
        ?int $branchId,
        Validator $validator,
        string $field = 'branch_id',
    ): void {
        if ($branchId === null) {
            if (! $user->hasCompanyScopedRole()) {
                $validator->errors()->add(
                    $field,
                    'Hanya role lingkup perusahaan yang dapat mengelola data global.',
                );
            }

            return;
        }

        $branch = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereKey($branchId)
            ->first();

        if ($branch === null || ! $user->canAccessBranch($branch)) {
            $validator->errors()->add(
                $field,
                'Anda tidak memiliki akses ke cabang tersebut.',
            );
        }
    }

    public function allows(User $user, ?int $branchId): bool
    {
        if ($branchId === null) {
            return $user->hasCompanyScopedRole();
        }

        $branch = Branch::query()
            ->where('company_id', $user->company_id)
            ->whereKey($branchId)
            ->first();

        return $branch !== null && $user->canAccessBranch($branch);
    }
}
