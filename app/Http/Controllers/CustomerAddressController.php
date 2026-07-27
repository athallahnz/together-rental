<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveCustomerAddressRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomerAddressController extends Controller
{
    public function store(
        SaveCustomerAddressRequest $request,
        Customer $customer,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        DB::transaction(function () use ($customer, $recorder, $request): void {
            $validated = $request->validated();
            $isPrimary = $validated['is_primary'] || ! $customer->addresses()->exists();

            if ($isPrimary) {
                $customer->addresses()->update(['is_primary' => false]);
            }

            $address = $customer->addresses()->create([
                ...$validated,
                'is_primary' => $isPrimary,
            ]);
            $recorder->record(
                $request,
                'customer.address_created',
                $address,
                null,
                $address->toArray(),
                $customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Alamat pelanggan berhasil ditambahkan.',
        ]);
    }

    public function update(
        SaveCustomerAddressRequest $request,
        CustomerAddress $customerAddress,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $customerAddress->toArray();

        DB::transaction(function () use (
            $customerAddress,
            $oldValues,
            $recorder,
            $request,
        ): void {
            $validated = $request->validated();

            if ($validated['is_primary']) {
                $customerAddress->customer
                    ->addresses()
                    ->whereKeyNot($customerAddress->id)
                    ->update(['is_primary' => false]);
            }

            $customerAddress->update($validated);

            if (! $customerAddress->customer->addresses()->where('is_primary', true)->exists()) {
                $customerAddress->update(['is_primary' => true]);
            }

            $recorder->record(
                $request,
                'customer.address_updated',
                $customerAddress,
                $oldValues,
                $customerAddress->fresh()->toArray(),
                $customerAddress->customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Alamat pelanggan berhasil diperbarui.',
        ]);
    }

    public function destroy(
        Request $request,
        CustomerAddress $customerAddress,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('customers.update');
        $this->guardCompany($request, $customerAddress);
        $customer = $customerAddress->customer;
        $wasPrimary = $customerAddress->is_primary;
        $oldValues = $customerAddress->toArray();

        DB::transaction(function () use (
            $customer,
            $customerAddress,
            $oldValues,
            $recorder,
            $request,
            $wasPrimary,
        ): void {
            $customerAddress->delete();

            if ($wasPrimary) {
                $customer->addresses()->first()?->update(['is_primary' => true]);
            }

            $recorder->record(
                $request,
                'customer.address_deleted',
                $customerAddress,
                $oldValues,
                null,
                $customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Alamat pelanggan berhasil dihapus.',
        ]);
    }

    private function guardCompany(
        Request $request,
        CustomerAddress $customerAddress,
    ): void {
        abort_unless(
            $customerAddress->customer()
                ->where('company_id', $request->user()->company_id)
                ->exists(),
            404,
        );
    }
}
