<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveRatePlanRequest;
use App\Models\RatePlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RatePlanController extends Controller
{
    public function store(
        SaveRatePlanRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $ratePlan = RatePlan::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);
        $recorder->record(
            $request,
            'catalog.rate_plan.created',
            $ratePlan,
            null,
            $ratePlan->toArray(),
            $ratePlan->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Rate plan {$ratePlan->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveRatePlanRequest $request,
        RatePlan $ratePlan,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        if (
            $ratePlan->branch_id !== $validated['branch_id']
            && ($ratePlan->productRates()->exists()
                || $ratePlan->packageRates()->exists()
                || $ratePlan->bookings()->exists()
                || $ratePlan->rentals()->exists())
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Scope rate plan tidak dapat diubah setelah digunakan.',
            ]);
        }

        $oldValues = $ratePlan->toArray();
        $ratePlan->update($validated);
        $recorder->record(
            $request,
            'catalog.rate_plan.updated',
            $ratePlan,
            $oldValues,
            $ratePlan->fresh()->toArray(),
            $ratePlan->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Rate plan {$ratePlan->name} berhasil diperbarui.",
        ]);
    }
}
