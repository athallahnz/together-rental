<?php

namespace App\Domain\Catalog\Intelligence;

use App\Models\Product;

class DisabledCatalogAiSuggestionProvider implements CatalogAiSuggestionProvider
{
    public function enabled(): bool
    {
        return false;
    }

    public function name(): ?string
    {
        return null;
    }

    public function suggest(Product $product, array $deterministicSuggestion): ?array
    {
        return null;
    }
}
