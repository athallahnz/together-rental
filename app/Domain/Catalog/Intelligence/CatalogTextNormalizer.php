<?php

namespace App\Domain\Catalog\Intelligence;

use Illuminate\Support\Str;

class CatalogTextNormalizer
{
    public function normalize(?string $value): string
    {
        $normalized = mb_strtoupper(Str::ascii(trim((string) $value)));
        $normalized = str_replace(['_', '–', '—'], [' ', '-', '-'], $normalized);
        $normalized = preg_replace('/[^A-Z0-9+.\-\/\s]/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    public function normalizedKey(?string $value): string
    {
        return str_replace(' ', '', $this->normalize($value));
    }

    public function containsAlias(string $normalizedName, string $normalizedAlias): bool
    {
        if ($normalizedAlias === '') {
            return false;
        }

        return preg_match(
            '/(?:^|\s)'.preg_quote($normalizedAlias, '/').'(?:\s|$)/i',
            $normalizedName,
        ) === 1;
    }

    public function cleanModel(string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $value = trim($value, " \t\n\r\0\x0B-+/");

        if ($value === '' || mb_strlen($value) < 2) {
            return null;
        }

        return mb_substr($value, 0, 120);
    }
}
