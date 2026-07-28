<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\UpdateBranchPublicProfileRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchPublicProfileController extends Controller
{
    /** @var list<string> */
    private const PUBLIC_KEYS = [
        'public_catalog_enabled',
        'public_whatsapp',
        'public_short_address',
        'public_maps_url',
        'public_instagram',
        'public_opening_hours',
        'public_logo_path',
        'public_hero_title',
        'public_hero_description',
    ];

    public function edit(Request $request, Branch $branch): Response
    {
        Gate::authorize('branches.manage');
        $this->guardCompany($request, $branch);

        return Inertia::render('branches/public-profile', [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => $branch->city,
                'phone' => $branch->phone,
                'address' => $branch->address,
                'is_active' => $branch->is_active,
            ],
            'profile' => $this->profile($branch),
            'previewUrl' => route('home', ['branch' => $branch->code]),
        ]);
    }

    public function update(
        UpdateBranchPublicProfileRequest $request,
        Branch $branch,
        ActivityRecorder $activityRecorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $oldValues = $this->profile($branch);
        $now = now();
        $rows = collect(self::PUBLIC_KEYS)
            ->map(function (string $key) use ($branch, $now, $validated): array {
                $value = $validated[$key] ?? null;

                return [
                    'branch_id' => $branch->id,
                    'key' => $key,
                    'value_type' => $key === 'public_catalog_enabled' ? 'boolean' : 'string',
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'is_public' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        DB::transaction(function () use (
            $activityRecorder,
            $branch,
            $oldValues,
            $request,
            $rows,
            $validated,
        ): void {
            DB::table('branch_settings')->upsert(
                $rows,
                ['branch_id', 'key'],
                ['value_type', 'value', 'is_public', 'updated_at'],
            );

            $activityRecorder->record(
                $request,
                'branch.public_profile.updated',
                $branch,
                $oldValues,
                $validated,
                $branch->id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $validated['public_catalog_enabled']
                ? "Katalog publik cabang {$branch->code} berhasil diaktifkan dan diperbarui."
                : "Katalog publik cabang {$branch->code} berhasil dinonaktifkan.",
        ]);
    }

    /** @return array<string, mixed> */
    private function profile(Branch $branch): array
    {
        $settings = DB::table('branch_settings')
            ->where('branch_id', $branch->id)
            ->whereIn('key', self::PUBLIC_KEYS)
            ->get(['key', 'value'])
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->key => $this->decode($row->value),
            ]);

        return [
            'public_catalog_enabled' => $this->boolean(
                $settings->get('public_catalog_enabled', false),
            ),
            'public_whatsapp' => (string) $settings->get(
                'public_whatsapp',
                preg_replace('/\D+/', '', (string) $branch->phone),
            ),
            'public_short_address' => (string) $settings->get(
                'public_short_address',
                $branch->address ?? '',
            ),
            'public_maps_url' => (string) $settings->get('public_maps_url', ''),
            'public_instagram' => ltrim((string) $settings->get('public_instagram', ''), '@'),
            'public_opening_hours' => (string) $settings->get(
                'public_opening_hours',
                '09.00–21.00 WIB',
            ),
            'public_logo_path' => (string) $settings->get(
                'public_logo_path',
                '/primary-logos.png',
            ),
            'public_hero_title' => (string) $settings->get(
                'public_hero_title',
                'Sewa alat kreatif tanpa ribet.',
            ),
            'public_hero_description' => (string) $settings->get(
                'public_hero_description',
                '',
            ),
        ];
    }

    private function guardCompany(Request $request, Branch $branch): void
    {
        abort_unless($branch->company_id === $request->user()->company_id, 404);
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
