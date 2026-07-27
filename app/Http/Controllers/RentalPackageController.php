<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Catalog\CatalogScope;
use App\Http\Requests\SaveRentalPackageRequest;
use App\Models\RentalPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RentalPackageController extends Controller
{
    public function store(
        SaveRentalPackageRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $package = RentalPackage::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);
        $recorder->record(
            $request,
            'catalog.package.created',
            $package,
            null,
            $package->toArray(),
            $package->branch_id,
        );

        return to_route('catalog.packages.show', $package)->with('toast', [
            'type' => 'success',
            'message' => "Paket {$package->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveRentalPackageRequest $request,
        RentalPackage $rentalPackage,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        if (
            $rentalPackage->branch_id !== $validated['branch_id']
            && ($rentalPackage->items()->exists() || $rentalPackage->rates()->exists())
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Scope paket tidak dapat diubah setelah memiliki item atau harga.',
            ]);
        }

        $oldValues = $rentalPackage->toArray();
        $rentalPackage->update($validated);
        $recorder->record(
            $request,
            'catalog.package.updated',
            $rentalPackage,
            $oldValues,
            $rentalPackage->fresh()->toArray(),
            $rentalPackage->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Paket {$rentalPackage->name} berhasil diperbarui.",
        ]);
    }

    public function archive(
        Request $request,
        RentalPackage $rentalPackage,
        CatalogScope $scope,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        abort_unless(
            $rentalPackage->company_id === $request->user()->company_id
                && $scope->allows($request->user(), $rentalPackage->branch_id),
            404,
        );
        $activeBooking = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('booking_items.package_id', $rentalPackage->id)
            ->whereNotIn('bookings.status', ['completed', 'cancelled', 'converted', 'void'])
            ->whereNull('bookings.deleted_at')
            ->exists();

        if ($activeBooking) {
            throw ValidationException::withMessages([
                'package' => 'Paket masih digunakan oleh booking aktif.',
            ]);
        }

        $oldValues = $rentalPackage->toArray();
        $rentalPackage->delete();
        $recorder->record(
            $request,
            'catalog.package.archived',
            $rentalPackage,
            $oldValues,
            null,
            $rentalPackage->branch_id,
        );

        return to_route('catalog.index', ['section' => 'packages'])->with('toast', [
            'type' => 'success',
            'message' => "Paket {$rentalPackage->name} berhasil diarsipkan.",
        ]);
    }
}
