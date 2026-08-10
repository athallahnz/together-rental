<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Rentals\RentalCollateralDocumentStorage;
use App\Domain\Rentals\RentalCollateralManager;
use App\Http\Requests\ReturnRentalCollateralRequest;
use App\Http\Requests\StoreRentalCollateralRequest;
use App\Models\Rental;
use App\Models\RentalCollateral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RentalCollateralController extends Controller
{
    public function store(
        StoreRentalCollateralRequest $request,
        Rental $rental,
        RentalCollateralManager $manager,
        RentalCollateralDocumentStorage $documents,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $rental);
        $prepared = $documents->prepareOne($request->validated());

        try {
            $collateral = $manager->receive($rental, $prepared['payload'], $request->user());
        } catch (Throwable $exception) {
            if ($prepared['path'] !== null) {
                $documents->cleanup([$prepared['path']]);
            }
            throw $exception;
        }

        $recorder->record(
            $request,
            'rental.collateral_received',
            $collateral,
            null,
            $this->audit($collateral),
            $rental->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Jaminan {$collateral->type} berhasil diterima.",
        ]);
    }

    public function markReturned(
        ReturnRentalCollateralRequest $request,
        Rental $rental,
        RentalCollateral $collateral,
        RentalCollateralManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $rental);
        $this->guardCollateral($rental, $collateral);
        $before = $this->audit($collateral);
        $returned = $manager->returnOne(
            $rental,
            $collateral,
            $request->user(),
            $request->validated('returned_at'),
        );

        $recorder->record(
            $request,
            'rental.collateral_returned',
            $returned,
            $before,
            $this->audit($returned),
            $rental->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Jaminan {$returned->type} berhasil dikembalikan.",
        ]);
    }

    public function document(
        Request $request,
        Rental $rental,
        RentalCollateral $collateral,
    ): StreamedResponse {
        Gate::authorize('rentals.view');
        $this->guardAccess($request, $rental);
        $this->guardCollateral($rental, $collateral);
        abort_unless(
            is_string($collateral->document_path)
                && Storage::disk('local')->exists($collateral->document_path),
            404,
        );

        return Storage::disk('local')->download(
            $collateral->document_path,
            $collateral->document_original_name ?? basename($collateral->document_path),
        );
    }

    private function guardAccess(Request $request, Rental $rental): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($rental->branch_id)->exists(),
            404,
        );
    }

    private function guardCollateral(Rental $rental, RentalCollateral $collateral): void
    {
        abort_unless($collateral->rental_id === $rental->id, 404);
    }

    /** @return array<string, mixed> */
    private function audit(RentalCollateral $collateral): array
    {
        return $collateral->only([
            'id',
            'rental_id',
            'customer_id',
            'type',
            'number',
            'holder_name',
            'status',
            'received_at',
            'received_by',
            'returned_at',
            'returned_by',
            'document_path',
        ]);
    }
}
