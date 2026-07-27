<?php

namespace App\Domain\LegacyImport;

use Carbon\CarbonImmutable;
use Throwable;

final class RentalV1Value
{
    public static function string(mixed $value, ?int $limit = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return $limit === null ? $normalized : mb_substr($normalized, 0, $limit);
    }

    public static function integer(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function decimal(mixed $value, string $default = '0.00'): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function boolean(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function date(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public static function dateTime(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return a datetime suitable for RentalV1 operational transactions.
     *
     * RentalV1 contains a few OLE Automation sentinel values around
     * 1899-12-30. Birth dates use date(), so legitimate historical dates are
     * not affected by this operational cutoff.
     */
    public static function operationalDateTime(mixed $value): ?string
    {
        $dateTime = self::dateTime($value);

        if ($dateTime === null) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::parse($dateTime);

            return $parsed->year >= 2000 ? $parsed->format('Y-m-d H:i:s') : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function gender(mixed $value): ?string
    {
        return match (mb_strtolower((string) self::string($value))) {
            'laki-laki', 'laki laki', 'l', 'male' => 'male',
            'perempuan', 'p', 'female' => 'female',
            default => null,
        };
    }

    public static function identityType(mixed $value): string
    {
        return match (mb_strtolower((string) self::string($value))) {
            'sim' => 'SIM',
            'kartu pelajar' => 'Kartu Pelajar',
            'kartu keluarga', 'kk' => 'Kartu Keluarga',
            'ktm' => 'KTM',
            default => self::string($value, 40) ?? 'KTP',
        };
    }

    public static function ratePlanCode(mixed $value): string
    {
        return match ((string) $value) {
            '0' => '6H',
            '1' => '12H',
            default => '1D',
        };
    }

    public static function bookingStatus(mixed $value): string
    {
        return match (mb_strtolower((string) $value)) {
            'batal' => 'cancelled',
            'pinjam' => 'converted',
            default => 'confirmed',
        };
    }

    public static function rentalStatus(mixed $value): string
    {
        return match (mb_strtolower((string) $value)) {
            'kembali' => 'returned',
            default => 'active',
        };
    }

    public static function extensionStatus(mixed $value): string
    {
        return match (mb_strtolower((string) $value)) {
            'kembali' => 'completed',
            default => 'approved',
        };
    }

    public static function productStatus(mixed $value): string
    {
        return mb_strtolower((string) $value) === 'keluar' ? 'rented' : 'available';
    }
}
