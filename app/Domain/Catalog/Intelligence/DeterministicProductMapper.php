<?php

namespace App\Domain\Catalog\Intelligence;

use App\Models\Product;

class DeterministicProductMapper
{
    public function __construct(
        private readonly CatalogTextNormalizer $normalizer,
    ) {}

    /**
     * @param  list<array{id: int, name: string, alias: string}>  $brandAliases
     * @return array{
     *     normalized_name: string,
     *     brand_id: int|null,
     *     brand: string|null,
     *     model: string|null,
     *     variant: string|null,
     *     confidence: float,
     *     source: string,
     *     status: string,
     *     reasoning: list<string>
     * }
     */
    public function map(Product $product, array $brandAliases): array
    {
        $normalized = $this->normalizer->normalize($product->name);
        $reasons = [];
        $brand = $this->detectExplicitBrand($normalized, $brandAliases);
        $brandSource = 'none';

        if ($brand !== null) {
            $brandSource = 'alias';
            $reasons[] = "Brand dikenali dari alias [{$brand['alias']}].";
        } elseif (filled($product->brand)) {
            $brand = $this->matchBrand(
                $this->normalizer->normalize($product->brand),
                $brandAliases,
            );
            $brandSource = $brand === null ? 'existing-text' : 'existing';
            $reasons[] = 'Brand menggunakan nilai produk yang sudah tersedia.';
        }

        if ($brand === null) {
            $inferred = $this->inferBrand($normalized, $brandAliases);
            if ($inferred !== null) {
                $brand = $inferred;
                $brandSource = 'inference';
                $reasons[] = 'Brand diinferensikan dari pola model yang khas.';
            }
        }

        $brandName = $brand['name'] ?? $this->cleanExisting($product->brand);
        $brandId = $brand['id'] ?? null;
        $variant = $this->extractVariant($normalized);
        $model = $this->extractModel(
            $normalized,
            $brandName,
            $brand['alias'] ?? null,
        );

        if ($model !== null) {
            $reasons[] = "Model diekstrak sebagai [{$model}].";
        } else {
            $model = $this->cleanExisting($product->model);
            if ($model !== null) {
                $reasons[] = 'Model menggunakan nilai produk yang sudah tersedia.';
            }
        }

        if ($variant !== null) {
            $reasons[] = "Varian dikenali sebagai [{$variant}].";
        }

        $confidence = $this->confidence($brandSource, $brandName, $model);
        $status = $confidence >= (float) config('catalog-intelligence.high_confidence_threshold', 85)
            ? 'pending'
            : 'needs_review';

        if ($brandName === null && $model === null) {
            $reasons[] = 'Nama belum cukup spesifik untuk dipetakan otomatis.';
        }

        return [
            'normalized_name' => $normalized,
            'brand_id' => $brandId,
            'brand' => $brandName,
            'model' => $model,
            'variant' => $variant,
            'confidence' => $confidence,
            'source' => 'rule',
            'status' => $status,
            'reasoning' => $reasons,
        ];
    }

    /**
     * @param  list<array{id: int, name: string, alias: string}>  $brandAliases
     * @return array{id: int, name: string, alias: string}|null
     */
    private function detectExplicitBrand(string $name, array $brandAliases): ?array
    {
        foreach ($brandAliases as $brand) {
            if ($this->normalizer->containsAlias($name, $brand['alias'])) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string, alias: string}>  $brandAliases
     * @return array{id: int, name: string, alias: string}|null
     */
    private function matchBrand(string $value, array $brandAliases): ?array
    {
        foreach ($brandAliases as $brand) {
            if ($value === $brand['alias']) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string, alias: string}>  $brandAliases
     * @return array{id: int, name: string, alias: string}|null
     */
    private function inferBrand(string $name, array $brandAliases): ?array
    {
        $inferences = [
            'Apple' => '/\bIPHON(?:E)?\b/',
            'Canon' => '/\b(?:EOS\s+)?(?:[0-9]{2,4}D|5D|6D|7D|RP|M[0-9]+)\b/',
            'Nikon' => '/\b(?:D[0-9]{3,4}|Z[0-9](?:\s+II)?|FTZ)\b/',
            'Fujifilm' => '/\bX[-\s]?(?:T|A|H|E)[-\s]?[0-9]+\b/',
            'Sony' => '/\b(?:A7[A-Z0-9\s-]*|A[0-9]{4}|FX30|RX100|NX[0-9]+|FDR\s*AX[0-9]+|[0-9]{2}-[0-9]{2,3}MM\s+GM)\b/',
            'DJI' => '/\b(?:MAVIC|OSMO|MINI\s*2|AIR\s*2S|POCKET\s*3)\b/',
            'Panasonic' => '/\b(?:LUMIX|HC[-\s]?X[0-9]+)\b/',
            'Godox' => '/\b(?:TT600|V850|X2T?)\b/',
        ];

        foreach ($inferences as $brandName => $pattern) {
            if (preg_match($pattern, $name) !== 1) {
                continue;
            }

            foreach ($brandAliases as $brand) {
                if ($brand['name'] === $brandName) {
                    return $brand;
                }
            }
        }

        return null;
    }

    private function extractModel(
        string $name,
        ?string $brandName,
        ?string $matchedAlias,
    ): ?string {
        if (preg_match('/\bIPHON(?:E)?\s+([0-9]{1,2}|XR)(?:\s*(PRO\s*MAX|PROMAX|PRO|PLUS|PM|\+))?/i', $name, $match) === 1) {
            $suffix = match (str_replace(' ', '', mb_strtoupper($match[2] ?? ''))) {
                'PROMAX', 'PM' => ' Pro Max',
                'PRO' => ' Pro',
                'PLUS', '+' => ' Plus',
                default => '',
            };

            return "iPhone {$match[1]}{$suffix}";
        }

        $patterns = [
            '/\b(MAVIC\s+(?:AIR\s+)?2S|AIR\s+2S|MINI\s*2|POCKET\s+[13]|OSMO\s+[A-Z0-9]+|ACTION\s+CAM\s+[0-9]+)\b/i',
            '/\b(TT600|V850(?:\s+II)?|X2T?)\b/i',
            '/\b(EOS\s+)?(RP|R[0-9]+|M[0-9]+|[0-9]{2,4}D|[567]D(?:\s+(?:MARK\s+)?(?:II|III|IV))?)\b/i',
            '/\b(D[0-9]{3,4}|Z[0-9](?:\s+II)?)\b/i',
            '/\b(A7(?:R|S|C)?(?:\s|-)?(?:II|III|IV)?|A[0-9]{4}|FX30|RX100|NX[0-9]+|FDR\s*AX[0-9A-Z]+)\b/i',
            '/\b(X[-\s]?(?:T|A|H|E)[-\s]?[0-9]+)\b/i',
            '/\b(HC[-\s]?X[0-9]+)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $match) === 1) {
                $model = mb_strtoupper(trim($match[count($match) - 1]));
                $model = preg_replace('/\s+/', ' ', $model) ?? $model;

                return $brandName === 'Canon' && ! str_starts_with($model, 'EOS ')
                    ? "EOS {$model}"
                    : $model;
            }
        }

        $working = $name;
        foreach (array_filter([$matchedAlias, $this->normalizer->normalize($brandName)]) as $token) {
            $working = preg_replace(
                '/(?:^|\s)'.preg_quote((string) $token, '/').'(?:\s|$)/',
                ' ',
                $working,
                1,
            ) ?? $working;
        }
        $working = preg_replace('/\b(?:LENSA|LENS|KAMERA|CAMERA)\b/', ' ', $working) ?? $working;
        $working = preg_replace('/\b(?:BODY\s+ONLY|BDOY\s+ONLY|TANPA\s+LENSA|BO)\b/', ' ', $working) ?? $working;
        $working = preg_replace('/\b(?:STOK\s+JB|JB\s*[0-9]*|GREY|GRAY|HITAM|PUTIH|KUNING)\b/', ' ', $working) ?? $working;
        $working = preg_replace('/\s+/', ' ', $working) ?? $working;

        if ($brandName === null && preg_match('/(?:MM|F[0-9]|T[0-9]{2,3}|V[0-9]{2,3}|SL[0-9]{2,3})/', $working) !== 1) {
            return null;
        }

        return $this->normalizer->cleanModel($working);
    }

    private function extractVariant(string $name): ?string
    {
        if (preg_match('/\b(?:BODY\s+ONLY|BDOY\s+ONLY|TANPA\s+LENSA|BO)\b/', $name) === 1) {
            return 'Body Only';
        }

        if (preg_match('/\bKIT(?:\s+([0-9]{1,3}-[0-9]{1,3}MM(?:\s+STM)?))?\b/', $name, $match) === 1) {
            return isset($match[1]) ? "Kit {$match[1]}" : 'Kit';
        }

        foreach ([
            'HITAM' => 'Black',
            'PUTIH' => 'White',
            'KUNING' => 'Yellow',
            'GREY' => 'Grey',
            'GRAY' => 'Grey',
        ] as $token => $variant) {
            if (preg_match('/\b'.$token.'\b/', $name) === 1) {
                return $variant;
            }
        }

        return null;
    }

    private function cleanExisting(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function confidence(string $brandSource, ?string $brand, ?string $model): float
    {
        $score = match ($brandSource) {
            'existing' => 98,
            'alias' => 92,
            'existing-text' => 82,
            'inference' => 72,
            default => 35,
        };

        if ($brand !== null && $model !== null) {
            $score += 5;
        } elseif ($model === null) {
            $score -= 25;
        } elseif ($brand === null) {
            $score = min($score, 55);
        }

        return (float) max(0, min(100, $score));
    }
}
