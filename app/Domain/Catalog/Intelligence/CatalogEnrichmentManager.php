<?php

namespace App\Domain\Catalog\Intelligence;

use App\Models\CatalogBrand;
use App\Models\CatalogBrandAlias;
use App\Models\CatalogEnrichmentCandidate;
use App\Models\CatalogEnrichmentRun;
use App\Models\CatalogModel;
use App\Models\CatalogModelAlias;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogEnrichmentManager
{
    public function __construct(
        private readonly CatalogTextNormalizer $normalizer,
        private readonly DeterministicProductMapper $mapper,
        private readonly CatalogAiSuggestionProvider $aiProvider,
    ) {}

    public function preview(User $actor, string $scope, bool $useAi): CatalogEnrichmentRun
    {
        return DB::transaction(function () use ($actor, $scope, $useAi): CatalogEnrichmentRun {
            $run = CatalogEnrichmentRun::query()->create([
                'company_id' => $actor->company_id,
                'status' => 'previewed',
                'scope' => $scope,
                'ai_requested' => $useAi,
                'ai_provider' => $useAi && $this->aiProvider->enabled()
                    ? $this->aiProvider->name()
                    : null,
                'generated_by' => $actor->id,
                'options' => [
                    'high_confidence_threshold' => $this->highConfidenceThreshold(),
                    'ai_available' => $this->aiProvider->enabled(),
                ],
            ]);
            $aliases = $this->brandAliases((int) $actor->company_id);
            $products = Product::query()
                ->where('company_id', $actor->company_id)
                ->when($scope === 'missing', function (Builder $query): void {
                    $query->where(function (Builder $missingQuery): void {
                        $missingQuery
                            ->where('enrichment_status', '!=', 'enriched')
                            ->orWhereNull('brand')
                            ->orWhere('brand', '')
                            ->orWhereNull('model')
                            ->orWhere('model', '');
                    });
                })
                ->orderBy('id')
                ->get();

            foreach ($products as $product) {
                $suggestion = $this->mapper->map($product, $aliases);

                if (
                    $useAi
                    && $this->aiProvider->enabled()
                    && $suggestion['confidence'] < $this->highConfidenceThreshold()
                ) {
                    $aiSuggestion = $this->aiProvider->suggest($product, $suggestion);
                    $suggestion = $this->mergeAiSuggestion($suggestion, $aiSuggestion);
                }

                CatalogEnrichmentCandidate::query()->create([
                    'run_id' => $run->id,
                    'product_id' => $product->id,
                    'normalized_source_name' => $suggestion['normalized_name'],
                    'suggested_brand_id' => $suggestion['brand_id'],
                    'suggested_brand' => $suggestion['brand'],
                    'suggested_model' => $suggestion['model'],
                    'suggested_variant' => $suggestion['variant'],
                    'confidence' => $suggestion['confidence'],
                    'source' => $suggestion['source'],
                    'status' => $suggestion['status'],
                    'reasoning' => $suggestion['reasoning'],
                    'original_brand_id' => $product->catalog_brand_id,
                    'original_model_id' => $product->catalog_model_id,
                    'original_brand' => $product->brand,
                    'original_model' => $product->model,
                    'original_variant' => $product->variant,
                    'original_enrichment_status' => $product->enrichment_status,
                    'original_enriched_at' => $product->enriched_at,
                ]);
            }

            $run->update(['generated_count' => $products->count()]);

            return $run->fresh();
        });
    }

    /**
     * @param  array{
     *     suggested_brand: string|null,
     *     suggested_model: string|null,
     *     suggested_variant: string|null,
     *     status: string
     * }  $values
     */
    public function review(
        CatalogEnrichmentCandidate $candidate,
        User $actor,
        array $values,
    ): CatalogEnrichmentCandidate {
        $this->guardMutableRun($candidate->run);
        $changed = $candidate->suggested_brand !== $values['suggested_brand']
            || $candidate->suggested_model !== $values['suggested_model']
            || $candidate->suggested_variant !== $values['suggested_variant'];

        $candidate->update([
            ...$values,
            'suggested_brand_id' => null,
            'suggested_model_id' => null,
            'source' => $changed ? 'manual' : $candidate->source,
            'confidence' => $changed ? 100 : $candidate->confidence,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
        $candidate->run->update(['status' => 'reviewing']);
        $this->refreshApprovedCount($candidate->run);

        return $candidate->fresh();
    }

    public function approveHighConfidence(
        CatalogEnrichmentRun $run,
        User $actor,
    ): int {
        $this->guardMutableRun($run);

        $count = $run->candidates()
            ->whereIn('status', ['pending', 'needs_review'])
            ->where('confidence', '>=', $this->highConfidenceThreshold())
            ->where(function (Builder $query): void {
                $query->whereNotNull('suggested_brand')->orWhereNotNull('suggested_model');
            })
            ->update([
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $run->update(['status' => 'reviewing']);
        $this->refreshApprovedCount($run);

        return $count;
    }

    public function execute(CatalogEnrichmentRun $run, User $actor): CatalogEnrichmentRun
    {
        return DB::transaction(function () use ($run, $actor): CatalogEnrichmentRun {
            $lockedRun = CatalogEnrichmentRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->guardMutableRun($lockedRun);
            $candidates = $lockedRun->candidates()
                ->where('status', 'approved')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages([
                    'enrichment' => 'Belum ada kandidat yang disetujui untuk dieksekusi.',
                ]);
            }

            foreach ($candidates as $candidate) {
                $product = Product::query()
                    ->where('company_id', $actor->company_id)
                    ->lockForUpdate()
                    ->findOrFail($candidate->product_id);
                $brand = $this->resolveBrand(
                    (int) $actor->company_id,
                    $candidate->suggested_brand,
                );
                $model = $this->resolveModel(
                    (int) $actor->company_id,
                    $brand,
                    $product,
                    $candidate->suggested_model,
                );

                $product->update([
                    'brand' => $brand?->name,
                    'catalog_brand_id' => $brand?->id,
                    'model' => $model?->name,
                    'catalog_model_id' => $model?->id,
                    'variant' => $this->nullableText($candidate->suggested_variant),
                    'enrichment_status' => 'enriched',
                    'enriched_at' => now(),
                ]);

                if ($model !== null) {
                    $this->rememberModelAlias($model, $product->name);
                }

                $candidate->update([
                    'status' => 'executed',
                    'resolved_brand_id' => $brand?->id,
                    'resolved_model_id' => $model?->id,
                    'executed_by' => $actor->id,
                    'executed_at' => now(),
                ]);
            }

            $verifiedCount = Product::query()
                ->whereIn('id', $candidates->pluck('product_id'))
                ->where('enrichment_status', 'enriched')
                ->count();
            $lockedRun->update([
                'status' => 'executed',
                'approved_count' => $candidates->count(),
                'executed_count' => $candidates->count(),
                'verified_count' => $verifiedCount,
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);

            return $lockedRun->fresh();
        });
    }

    public function rollback(CatalogEnrichmentRun $run, User $actor): CatalogEnrichmentRun
    {
        return DB::transaction(function () use ($run, $actor): CatalogEnrichmentRun {
            $lockedRun = CatalogEnrichmentRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->status !== 'executed') {
                throw ValidationException::withMessages([
                    'enrichment' => 'Hanya run yang sudah dieksekusi yang dapat di-rollback.',
                ]);
            }

            $skipped = 0;
            $candidates = $lockedRun->candidates()
                ->where('status', 'executed')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $candidate) {
                $product = Product::query()
                    ->where('company_id', $actor->company_id)
                    ->lockForUpdate()
                    ->find($candidate->product_id);

                if (
                    $product === null
                    || $product->catalog_brand_id !== $candidate->resolved_brand_id
                    || $product->catalog_model_id !== $candidate->resolved_model_id
                ) {
                    $skipped++;

                    continue;
                }

                $product->update([
                    'brand' => $candidate->original_brand,
                    'catalog_brand_id' => $candidate->original_brand_id,
                    'model' => $candidate->original_model,
                    'catalog_model_id' => $candidate->original_model_id,
                    'variant' => $candidate->original_variant,
                    'enrichment_status' => $candidate->original_enrichment_status,
                    'enriched_at' => $candidate->original_enriched_at,
                ]);
                $candidate->update(['status' => 'rolled_back']);
            }

            $lockedRun->update([
                'status' => 'rolled_back',
                'rollback_skipped_count' => $skipped,
                'rolled_back_by' => $actor->id,
                'rolled_back_at' => now(),
            ]);

            return $lockedRun->fresh();
        });
    }

    /** @return list<array{id: int, name: string, alias: string}> */
    private function brandAliases(int $companyId): array
    {
        return CatalogBrandAlias::query()
            ->select([
                'catalog_brands.id',
                'catalog_brands.name',
                'catalog_brand_aliases.normalized_alias as alias',
            ])
            ->join('catalog_brands', 'catalog_brands.id', '=', 'catalog_brand_aliases.catalog_brand_id')
            ->where('catalog_brands.company_id', $companyId)
            ->where('catalog_brands.is_active', true)
            ->orderByRaw('length(catalog_brand_aliases.normalized_alias) desc')
            ->get()
            ->map(fn ($alias): array => [
                'id' => (int) $alias->id,
                'name' => (string) $alias->name,
                'alias' => (string) $alias->alias,
            ])
            ->all();
    }

    private function resolveBrand(int $companyId, ?string $suggestedBrand): ?CatalogBrand
    {
        $name = $this->nullableText($suggestedBrand);

        if ($name === null) {
            return null;
        }

        $normalized = $this->normalizer->normalize($name);
        $brand = CatalogBrand::query()->firstOrCreate(
            ['company_id' => $companyId, 'normalized_name' => $normalized],
            ['name' => $name, 'is_active' => true],
        );
        CatalogBrandAlias::query()->firstOrCreate(
            [
                'catalog_brand_id' => $brand->id,
                'normalized_alias' => $normalized,
            ],
            ['alias' => $name],
        );

        return $brand;
    }

    private function resolveModel(
        int $companyId,
        ?CatalogBrand $brand,
        Product $product,
        ?string $suggestedModel,
    ): ?CatalogModel {
        $name = $this->nullableText($suggestedModel);

        if ($name === null) {
            return null;
        }

        $normalized = $this->normalizer->normalizedKey($name);
        $query = CatalogModel::query()
            ->where('company_id', $companyId)
            ->where('normalized_name', $normalized);
        $brand === null
            ? $query->whereNull('catalog_brand_id')
            : $query->where('catalog_brand_id', $brand->id);
        $model = $query->first();

        if ($model === null) {
            $model = CatalogModel::query()->create([
                'company_id' => $companyId,
                'catalog_brand_id' => $brand?->id,
                'category_id' => $product->category_id,
                'name' => $name,
                'normalized_name' => $normalized,
                'is_active' => true,
            ]);
        }

        CatalogModelAlias::query()->firstOrCreate(
            [
                'catalog_model_id' => $model->id,
                'normalized_alias' => $normalized,
            ],
            ['alias' => $name],
        );

        return $model;
    }

    private function rememberModelAlias(CatalogModel $model, string $sourceName): void
    {
        $alias = mb_substr(trim($sourceName), 0, 180);
        $normalized = mb_substr($this->normalizer->normalize($alias), 0, 200);

        if ($alias === '' || $normalized === '') {
            return;
        }

        CatalogModelAlias::query()->firstOrCreate(
            [
                'catalog_model_id' => $model->id,
                'normalized_alias' => $normalized,
            ],
            ['alias' => $alias],
        );
    }

    private function refreshApprovedCount(CatalogEnrichmentRun $run): void
    {
        $run->update([
            'approved_count' => $run->candidates()->where('status', 'approved')->count(),
        ]);
    }

    private function guardMutableRun(CatalogEnrichmentRun $run): void
    {
        if (in_array($run->status, ['executed', 'rolled_back'], true)) {
            throw ValidationException::withMessages([
                'enrichment' => 'Run ini sudah ditutup dan tidak dapat diubah.',
            ]);
        }
    }

    private function highConfidenceThreshold(): float
    {
        return (float) config('catalog-intelligence.high_confidence_threshold', 85);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $deterministic
     * @param  array<string, mixed>|null  $ai
     * @return array<string, mixed>
     */
    private function mergeAiSuggestion(array $deterministic, ?array $ai): array
    {
        if ($ai === null) {
            return $deterministic;
        }

        $confidence = max(0, min(100, (float) ($ai['confidence'] ?? 0)));
        if ($confidence <= (float) $deterministic['confidence']) {
            return $deterministic;
        }

        $aiReasoning = is_array($ai['reasoning'] ?? null)
            ? $ai['reasoning']
            : [];

        return [
            ...$deterministic,
            'brand_id' => null,
            'brand' => $this->nullableText($ai['brand'] ?? null),
            'model' => $this->nullableText($ai['model'] ?? null),
            'variant' => $this->nullableText($ai['variant'] ?? null),
            'confidence' => $confidence,
            'source' => 'ai',
            'status' => $confidence >= $this->highConfidenceThreshold()
                ? 'pending'
                : 'needs_review',
            'reasoning' => [
                ...$deterministic['reasoning'],
                ...array_values(array_filter(
                    $aiReasoning,
                    fn ($reason): bool => is_string($reason),
                )),
            ],
        ];
    }
}
