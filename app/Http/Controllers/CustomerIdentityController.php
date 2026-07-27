<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveCustomerIdentityRequest;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CustomerIdentityController extends Controller
{
    public function store(
        SaveCustomerIdentityRequest $request,
        Customer $customer,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $identity = DB::transaction(function () use (
            $customer,
            $recorder,
            $request,
        ): CustomerIdentity {
            $validated = $request->validated();
            $isPrimary = $validated['is_primary'] || ! $customer->identities()->exists();

            if ($isPrimary) {
                $customer->identities()->update(['is_primary' => false]);
            }

            $identity = $customer->identities()->create([
                ...$validated,
                'is_primary' => $isPrimary,
            ]);
            $recorder->record(
                $request,
                'customer.identity_created',
                $identity,
                null,
                $identity->toArray(),
                $customer->registered_branch_id,
            );

            return $identity;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Identitas {$identity->number} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SaveCustomerIdentityRequest $request,
        CustomerIdentity $customerIdentity,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $customerIdentity->toArray();

        DB::transaction(function () use (
            $customerIdentity,
            $oldValues,
            $recorder,
            $request,
        ): void {
            $validated = $request->validated();

            if ($validated['is_primary']) {
                $customerIdentity->customer
                    ->identities()
                    ->whereKeyNot($customerIdentity->id)
                    ->update(['is_primary' => false]);
            }

            $verificationFieldsChanged = collect([
                'type',
                'number',
                'name_on_identity',
                'expires_at',
            ])->contains(
                fn (string $field): bool => (string) ($customerIdentity->{$field} ?? '')
                    !== (string) ($validated[$field] ?? ''),
            );
            $customerIdentity->update([
                ...$validated,
                ...($verificationFieldsChanged ? [
                    'verified_at' => null,
                    'verified_by' => null,
                ] : []),
            ]);

            if (! $customerIdentity->customer->identities()->where('is_primary', true)->exists()) {
                $customerIdentity->update(['is_primary' => true]);
            }

            $recorder->record(
                $request,
                'customer.identity_updated',
                $customerIdentity,
                $oldValues,
                $customerIdentity->fresh()->toArray(),
                $customerIdentity->customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Identitas pelanggan berhasil diperbarui.',
        ]);
    }

    public function verify(
        Request $request,
        CustomerIdentity $customerIdentity,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('customers.verify');
        $this->guardCompany($request, $customerIdentity);

        if ($customerIdentity->verified_at !== null) {
            return back()->with('toast', [
                'type' => 'info',
                'message' => 'Identitas pelanggan sudah diverifikasi.',
            ]);
        }

        if ($customerIdentity->expires_at?->isPast()) {
            throw ValidationException::withMessages([
                'identity' => 'Identitas yang sudah kedaluwarsa tidak dapat diverifikasi.',
            ]);
        }

        $customerIdentity->update([
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);
        $recorder->record(
            $request,
            'customer.identity_verified',
            $customerIdentity,
            ['verified_at' => null],
            [
                'verified_at' => $customerIdentity->verified_at,
                'verified_by' => $customerIdentity->verified_by,
            ],
            $customerIdentity->customer->registered_branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Identitas pelanggan berhasil diverifikasi.',
        ]);
    }

    public function destroy(
        Request $request,
        CustomerIdentity $customerIdentity,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('customers.update');
        $this->guardCompany($request, $customerIdentity);
        $customer = $customerIdentity->customer;
        $wasPrimary = $customerIdentity->is_primary;
        $oldValues = $customerIdentity->toArray();

        DB::transaction(function () use (
            $customer,
            $customerIdentity,
            $oldValues,
            $recorder,
            $request,
            $wasPrimary,
        ): void {
            $customerIdentity->delete();

            if ($wasPrimary) {
                $customer->identities()->first()?->update(['is_primary' => true]);
            }

            $recorder->record(
                $request,
                'customer.identity_deleted',
                $customerIdentity,
                $oldValues,
                null,
                $customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Identitas pelanggan berhasil dihapus.',
        ]);
    }

    private function guardCompany(
        Request $request,
        CustomerIdentity $customerIdentity,
    ): void {
        abort_unless(
            $customerIdentity->customer()
                ->where('company_id', $request->user()->company_id)
                ->exists(),
            404,
        );
    }
}
