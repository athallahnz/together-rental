<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Transfers\TransferSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\UpdateBranchTransferSettingsRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchTransferSettingsController extends Controller
{
    public function edit(Request $request, TransferSettings $settings): Response
    {
        Gate::authorize('transfers.settings');
        $branches = Branch::query()
            ->where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $selectedBranchId = $request->integer('branch_id') ?: (int) $request->user()->current_branch_id;
        abort_unless($branches->contains('id', $selectedBranchId), 404);

        return Inertia::render('transfers/settings', [
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'settings' => $settings->all($selectedBranchId),
        ]);
    }

    public function update(
        UpdateBranchTransferSettingsRequest $request,
        TransferSettings $settings,
    ): RedirectResponse {
        $settings->update($request->integer('branch_id'), $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pengaturan kamera dan dokumen transfer berhasil diperbarui.',
        ]);
    }
}
