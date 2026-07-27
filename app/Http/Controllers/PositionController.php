<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SavePositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function store(
        SavePositionRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $position = Position::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);
        $recorder->record(
            $request,
            'position.created',
            $position,
            null,
            $position->toArray(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Jabatan {$position->name} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SavePositionRequest $request,
        Position $position,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardCompany($request, $position);
        $oldValues = $position->toArray();
        $position->update($request->validated());
        $recorder->record(
            $request,
            'position.updated',
            $position,
            $oldValues,
            $position->fresh()->toArray(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Jabatan {$position->name} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        Position $position,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('users.manage');
        $this->guardCompany($request, $position);

        if ($position->is_active && $position->employees()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'position' => 'Jabatan masih digunakan oleh karyawan aktif.',
            ]);
        }

        $oldValues = ['is_active' => $position->is_active];
        $position->update(['is_active' => ! $position->is_active]);
        $recorder->record(
            $request,
            $position->is_active ? 'position.activated' : 'position.deactivated',
            $position,
            $oldValues,
            ['is_active' => $position->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Status jabatan {$position->name} berhasil diperbarui.",
        ]);
    }

    private function guardCompany(Request $request, Position $position): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $position->company_id === $request->user()->company_id,
            404,
        );
    }
}
