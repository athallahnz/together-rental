<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Catalog\Intelligence\CatalogAiSuggestionProvider;
use App\Domain\Catalog\Intelligence\CatalogEnrichmentManager;
use App\Models\CatalogBrand;
use App\Models\CatalogEnrichmentCandidate;
use App\Models\CatalogEnrichmentRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogIntelligenceController extends Controller
{
    public function index(
        Request $request,
        CatalogAiSuggestionProvider $aiProvider,
    ): Response {
        $this->authorizeCompanyManager($request);
        $run = $this->resolveRun($request);
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $candidates = null;
        $summary = $this->emptySummary();

        if ($run !== null) {
            $base = $run->candidates();
            $summary = [
                'total' => (clone $base)->count(),
                'high_confidence' => (clone $base)
                    ->where('confidence', '>=', config('catalog-intelligence.high_confidence_threshold', 85))
                    ->count(),
                'needs_review' => (clone $base)->where('status', 'needs_review')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'executed' => (clone $base)->where('status', 'executed')->count(),
            ];
            $candidates = $base
                ->when(
                    in_array($status, ['pending', 'needs_review', 'approved', 'rejected', 'executed', 'rolled_back'], true),
                    fn (Builder $query) => $query->where('status', $status),
                )
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->whereHas('product', function (Builder $productQuery) use ($search): void {
                        $productQuery
                            ->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->with('product:id,company_id,category_id,sku,name,brand,model,variant,enrichment_status')
                ->orderByRaw("case status when 'needs_review' then 0 when 'pending' then 1 when 'approved' then 2 else 3 end")
                ->orderByDesc('confidence')
                ->paginate(30)
                ->withQueryString();
        }

        return Inertia::render('catalog/intelligence', [
            'run' => $run,
            'candidates' => $candidates,
            'summary' => $summary,
            'filters' => [
                'run_id' => $run?->id,
                'status' => $status,
                'search' => $search,
            ],
            'history' => CatalogEnrichmentRun::query()
                ->where('company_id', $request->user()->company_id)
                ->latest()
                ->limit(10)
                ->get(),
            'brands' => CatalogBrand::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->withCount(['products', 'models'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'logo_path', 'sort_order']),
            'ai' => [
                'enabled' => $aiProvider->enabled(),
                'provider' => $aiProvider->name(),
            ],
            'threshold' => (float) config('catalog-intelligence.high_confidence_threshold', 85),
        ]);
    }

    public function generate(
        Request $request,
        CatalogEnrichmentManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->authorizeCompanyManager($request);
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['missing', 'all'])],
            'use_ai' => ['required', 'boolean'],
        ]);
        $run = $manager->preview(
            $request->user(),
            (string) $validated['scope'],
            (bool) $validated['use_ai'],
        );
        $recorder->record(
            $request,
            'catalog.enrichment.previewed',
            $run,
            null,
            [
                'scope' => $run->scope,
                'generated_count' => $run->generated_count,
                'ai_requested' => $run->ai_requested,
            ],
        );

        return to_route('catalog.intelligence.index', ['run_id' => $run->id])
            ->with('toast', [
                'type' => 'success',
                'message' => "{$run->generated_count} kandidat mapping berhasil dibuat.",
            ]);
    }

    public function review(
        Request $request,
        CatalogEnrichmentCandidate $candidate,
        CatalogEnrichmentManager $manager,
    ): RedirectResponse {
        $this->authorizeCandidate($request, $candidate);
        $validated = $request->validate([
            'suggested_brand' => ['nullable', 'string', 'max:100'],
            'suggested_model' => ['nullable', 'string', 'max:120'],
            'suggested_variant' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ]);
        $manager->review($candidate, $request->user(), [
            'suggested_brand' => $this->nullableText($validated['suggested_brand'] ?? null),
            'suggested_model' => $this->nullableText($validated['suggested_model'] ?? null),
            'suggested_variant' => $this->nullableText($validated['suggested_variant'] ?? null),
            'status' => $validated['status'],
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kandidat mapping berhasil diperbarui.',
        ]);
    }

    public function approveHighConfidence(
        Request $request,
        CatalogEnrichmentRun $run,
        CatalogEnrichmentManager $manager,
    ): RedirectResponse {
        $this->authorizeRun($request, $run);
        $count = $manager->approveHighConfidence($run, $request->user());

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$count} kandidat confidence tinggi disetujui.",
        ]);
    }

    public function execute(
        Request $request,
        CatalogEnrichmentRun $run,
        CatalogEnrichmentManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->authorizeRun($request, $run);
        $result = $manager->execute($run, $request->user());
        $recorder->record(
            $request,
            'catalog.enrichment.executed',
            $result,
            ['status' => $run->status],
            [
                'status' => $result->status,
                'executed_count' => $result->executed_count,
                'verified_count' => $result->verified_count,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$result->verified_count} produk berhasil dipetakan dan diverifikasi.",
        ]);
    }

    public function rollback(
        Request $request,
        CatalogEnrichmentRun $run,
        CatalogEnrichmentManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->authorizeRun($request, $run);
        $result = $manager->rollback($run, $request->user());
        $recorder->record(
            $request,
            'catalog.enrichment.rolled_back',
            $result,
            ['status' => 'executed'],
            [
                'status' => $result->status,
                'skipped_count' => $result->rollback_skipped_count,
            ],
        );

        return back()->with('toast', [
            'type' => $result->rollback_skipped_count === 0 ? 'success' : 'warning',
            'message' => $result->rollback_skipped_count === 0
                ? 'Hasil enrichment berhasil dikembalikan.'
                : "{$result->rollback_skipped_count} produk dilewati karena telah berubah setelah execute.",
        ]);
    }

    private function resolveRun(Request $request): ?CatalogEnrichmentRun
    {
        $query = CatalogEnrichmentRun::query()
            ->where('company_id', $request->user()->company_id);

        return $request->integer('run_id') > 0
            ? $query->whereKey($request->integer('run_id'))->firstOrFail()
            : $query->latest()->first();
    }

    private function authorizeCompanyManager(Request $request): void
    {
        Gate::authorize('products.manage');
        abort_unless(
            $request->user()->company_id !== null
                && $request->user()->hasCompanyScopedRole(),
            403,
        );
    }

    private function authorizeRun(Request $request, CatalogEnrichmentRun $run): void
    {
        $this->authorizeCompanyManager($request);
        abort_unless($run->company_id === $request->user()->company_id, 404);
    }

    private function authorizeCandidate(
        Request $request,
        CatalogEnrichmentCandidate $candidate,
    ): void {
        $candidate->loadMissing('run');
        $this->authorizeRun($request, $candidate->run);
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'high_confidence' => 0,
            'needs_review' => 0,
            'approved' => 0,
            'executed' => 0,
        ];
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
