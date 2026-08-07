<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\AssetScheduleService;
use App\Domain\PublicCatalog\PublicCatalogService;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AssetCalendarController extends Controller
{
    public function show(
        Request $request,
        Asset $asset,
        AssetScheduleService $schedule,
    ): JsonResponse {
        Gate::authorize('products.view');
        $asset->loadMissing('product:id,company_id,name,tracking_type', 'currentBranch:id,company_id,code,name,city,timezone');
        $user = $request->user();

        abort_unless(
            $user->company_id !== null
                && $asset->product->company_id === $user->company_id
                && $user->accessibleBranches()->whereKey($asset->current_branch_id)->exists(),
            404,
        );

        [$startsAt, $endsAt, $month] = $this->monthRange(
            $request->string('month')->toString(),
            $asset->currentBranch?->timezone ?: 'Asia/Jakarta',
        );

        return response()->json([
            'data' => [
                ...$schedule->calendar($asset, $startsAt, $endsAt),
                'month' => $month,
                'product' => [
                    'id' => $asset->product->id,
                    'name' => $asset->product->name,
                ],
            ],
        ]);
    }

    public function publicProduct(
        Request $request,
        string $slug,
        PublicCatalogService $catalog,
        AssetScheduleService $schedule,
    ): JsonResponse {
        $branchCode = $request->string('branch')->toString() ?: null;
        $payload = $catalog->product($slug, $branchCode);
        /** @var array<string, mixed> $branchPayload */
        $branchPayload = $payload['branch'];
        /** @var array<string, mixed> $productPayload */
        $productPayload = $payload['product'];

        abort_unless(($productPayload['tracking_type'] ?? null) === 'serialized', 404);

        $branch = Branch::query()->findOrFail((int) $branchPayload['id']);
        $product = Product::query()->findOrFail((int) $productPayload['id']);
        [$startsAt, $endsAt, $month] = $this->monthRange(
            $request->string('month')->toString(),
            $branch->timezone ?: 'Asia/Jakarta',
        );

        /** @var Collection<int, Asset> $assets */
        $assets = Asset::query()
            ->where('product_id', $product->id)
            ->where('current_branch_id', $branch->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotIn('condition', ['lost', 'retired'])
            ->whereNotIn('status', ['lost', 'retired', 'inactive'])
            ->with('currentBranch:id,code,name,city,timezone')
            ->orderBy('asset_code')
            ->get();

        $units = $assets
            ->values()
            ->map(fn (Asset $asset, int $index): array => [
                'key' => 'unit-'.($index + 1),
                'label' => 'Unit '.($index + 1),
                'current_status' => $asset->status,
                'condition' => $asset->condition,
            ])
            ->all();

        $requestedUnit = max(1, $request->integer('unit', 1));
        $selectedIndex = $assets->isEmpty()
            ? null
            : min($requestedUnit - 1, $assets->count() - 1);
        $selectedAsset = $selectedIndex === null ? null : $assets->values()->get($selectedIndex);
        $selectedUnit = null;

        if ($selectedAsset instanceof Asset) {
            $calendar = $schedule->calendar($selectedAsset, $startsAt, $endsAt, true);
            $selectedUnit = [
                'key' => 'unit-'.($selectedIndex + 1),
                'label' => 'Unit '.($selectedIndex + 1),
                'current_status' => $selectedAsset->status,
                'condition' => $selectedAsset->condition,
                'events' => $calendar['events'],
            ];
        }

        return response()->json([
            'data' => [
                'month' => $month,
                'branch' => [
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'city' => $branch->city,
                ],
                'product' => [
                    'slug' => $product->slug,
                    'name' => $product->name,
                ],
                'units' => $units,
                'selected_unit' => $selectedUnit,
                'privacy_note' => 'Identitas unit, nomor booking, rental, dan pelanggan disembunyikan pada kalender publik.',
            ],
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable, string} */
    private function monthRange(string $requestedMonth, string $timezone): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $requestedMonth) === 1
            ? $requestedMonth
            : CarbonImmutable::now($timezone)->format('Y-m');
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
        } catch (\Throwable) {
            abort(422, 'Bulan kalender tidak valid.');
        }

        abort_unless(
            $date instanceof CarbonImmutable && $date->format('Y-m') === $month,
            422,
            'Bulan kalender tidak valid.',
        );
        $startsAt = $date->startOfMonth();
        $endsAt = $date->endOfMonth();

        abort_if(
            $startsAt->isBefore(CarbonImmutable::now($timezone)->subMonthsNoOverflow(12)->startOfMonth())
                || $startsAt->isAfter(CarbonImmutable::now($timezone)->addMonthsNoOverflow(12)->startOfMonth()),
            422,
            'Kalender hanya dapat dilihat dalam rentang 12 bulan sebelum atau sesudah bulan berjalan.',
        );

        return [$startsAt, $endsAt, $month];
    }
}
