<?php

namespace App\Domain\Catalog\Intelligence;

use App\Models\Product;

interface CatalogAiSuggestionProvider
{
    public function enabled(): bool;

    public function name(): ?string;

    /**
     * @param  array<string, mixed>  $deterministicSuggestion
     * @return array{
     *     brand?: string|null,
     *     model?: string|null,
     *     variant?: string|null,
     *     confidence?: float,
     *     reasoning?: list<string>
     * }|null
     */
    public function suggest(Product $product, array $deterministicSuggestion): ?array;
}
