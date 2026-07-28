<?php

namespace App\Domain\PublicCatalog;

use App\Models\Branch;
use App\Models\PackageRate;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RentalPackage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicAvailabilityService
{
    /** @var list<string> */
    private const NON_BLOCKING_BOOKING_STATUSES = [
        'draft',
        'cancelled',
        'expired',
        'converted',
        'completed',
        'rejected',
        'void',
    ];

    /** @var list<string> */
    private const NON_BLOCKING_RENTAL_STATUSES = [
        'draft',
        'cancelled',
        'void',
        'rejected',
        'returned',
        'completed',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function check(array $input): array
    {
        $branch = $this->publicBranch((string) $input['branch']);
        $timezone = $branch->timezone ?: 'Asia/Jakarta';
        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d\\TH:i',
            (string) $input['starts_at'],
            $timezone,
        );
        $endsAt = CarbonImmutable::createFromFormat(
            'Y-m-d\\TH:i',
            (string) $input['ends_at'],
            $timezone,
        );

        if ($startsAt === false || $endsAt === false || ! $endsAt->isAfter($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Periode rental tidak valid.',
            ]);
        }

        if ($startsAt->isBefore(CarbonImmutable::now($timezone)->subMinutes(15))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Waktu mulai tidak boleh berada di masa lalu.',
            ]);
        }

        if ($startsAt->diffInDays($endsAt) > 31) {
            throw ValidationException::withMessages([
                'ends_at' => 'Periode pengecekan maksimal 31 hari.',
            ]);
        }

        $quantity = (int) $input['quantity'];
        $rateId = isset($input['rate_id']) ? (int) $input['rate_id'] : null;

        if ((string) $input['type'] === 'package') {
            return $this->checkPackage(
                $branch,
                (string) $input['slug'],
                $startsAt,
                $endsAt,
                $quantity,
                $rateId,
            );
        }

        return $this->checkProduct(
            $branch,
            (string) $input['slug'],
            $startsAt,
            $endsAt,
            $quantity,
            $rateId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function checkProduct(
        Branch $branch,
        string $slug,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $quantity,
        ?int $rateId,
    ): array {
        $product = Product::query()
            ->where('company_id', $branch->company_id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_rentable', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $category) => $category
                        ->where('is_active', true)
                        ->where('is_public', true)
                        ->whereNull('deleted_at'));
            })
            ->firstOrFail();

        $availability = $this->productAvailability(
            $product,
            $branch,
            $startsAt,
            $endsAt,
            $quantity,
        );
        $rate = $this->productRate(
            $product,
            $branch,
            $startsAt,
            $rateId,
        );
        $estimate = $this->estimate($rate, $startsAt, $endsAt, $quantity);
        $period = $this->period($startsAt, $endsAt);
        $message = $this->inquiryMessage(
            branch: $branch,
            itemType: 'Produk',
            itemName: $product->name,
            quantity: $quantity,
            period: $period,
            availability: $availability,
            rate: $rate,
            estimate: $estimate,
        );

        return [
            'type' => 'product',
            'slug' => $product->slug,
            'name' => $product->name,
            'branch' => $this->branchPayload($branch),
            'period' => $period,
            'requested_quantity' => $quantity,
            'availability' => $availability,
            'rate' => $rate,
            'estimate' => $estimate,
            'items' => [],
            'inquiry_url' => $this->whatsappUrl($branch, $message),
            'requires_confirmation' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPackage(
        Branch $branch,
        string $slug,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $quantity,
        ?int $rateId,
    ): array {
        $package = RentalPackage::query()
            ->where('company_id', $branch->company_id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->where(function (Builder $scope) use ($branch): void {
                $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->where(function (Builder $dates) use ($startsAt): void {
                $dates
                    ->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $startsAt->toDateString());
            })
            ->where(function (Builder $dates) use ($startsAt): void {
                $dates
                    ->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $startsAt->toDateString());
            })
            ->with([
                'items' => fn ($items) => $items
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'product' => fn ($product) => $product
                            ->where('company_id', $branch->company_id)
                            ->where('is_active', true)
                            ->where('is_rentable', true)
                            ->where('is_public', true)
                            ->whereNull('deleted_at'),
                    ]),
            ])
            ->firstOrFail();

        $items = $package->items
            ->filter(fn ($item): bool => $item->product !== null)
            ->map(function ($item) use ($branch, $startsAt, $endsAt, $quantity): array {
                $neededPerPackage = max((int) $item->quantity, 1);
                $requestedUnits = $neededPerPackage * $quantity;
                $availability = $this->productAvailability(
                    $item->product,
                    $branch,
                    $startsAt,
                    $endsAt,
                    $requestedUnits,
                );

                return [
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'quantity_per_package' => $neededPerPackage,
                    'requested_units' => $requestedUnits,
                    'is_optional' => (bool) $item->is_optional,
                    'total_units' => (int) $availability['total_units'],
                    'available_units' => (int) $availability['available_units'],
                    'status' => $availability['status'],
                    'label' => $availability['label'],
                ];
            })
            ->values();
        $requiredItems = $items->filter(fn (array $item): bool => ! $item['is_optional']);
        $totalPackages = $requiredItems->isEmpty()
            ? 0
            : (int) $requiredItems
                ->map(fn (array $item): int => intdiv(
                    (int) $item['total_units'],
                    max((int) $item['quantity_per_package'], 1),
                ))
                ->min();
        $availablePackages = $requiredItems->isEmpty()
            ? 0
            : (int) $requiredItems
                ->map(fn (array $item): int => intdiv(
                    (int) $item['available_units'],
                    max((int) $item['quantity_per_package'], 1),
                ))
                ->min();
        $availability = $this->availabilityPayload(
            total: $totalPackages,
            reserved: max(0, $totalPackages - $availablePackages),
            rented: 0,
            available: $availablePackages,
            requested: $quantity,
        );
        $rate = $this->packageRate(
            $package,
            $branch,
            $rateId,
        );
        $estimate = $this->estimate($rate, $startsAt, $endsAt, $quantity);
        $period = $this->period($startsAt, $endsAt);
        $message = $this->inquiryMessage(
            branch: $branch,
            itemType: 'Paket',
            itemName: $package->name,
            quantity: $quantity,
            period: $period,
            availability: $availability,
            rate: $rate,
            estimate: $estimate,
        );

        return [
            'type' => 'package',
            'slug' => $package->slug,
            'name' => $package->name,
            'branch' => $this->branchPayload($branch),
            'period' => $period,
            'requested_quantity' => $quantity,
            'availability' => $availability,
            'rate' => $rate,
            'estimate' => $estimate,
            'items' => $items->all(),
            'inquiry_url' => $this->whatsappUrl($branch, $message),
            'requires_confirmation' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productAvailability(
        Product $product,
        Branch $branch,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $requested,
    ): array {
        $total = $product->tracking_type === 'serialized'
            ? $this->serializedCapacity($product, $branch)
            : $this->quantityCapacity($product, $branch);
        $reserved = $this->bookingDemand(
            $product,
            $branch,
            $startsAt,
            $endsAt,
        );
        $rented = $this->rentalDemand(
            $product,
            $branch,
            $startsAt,
            $endsAt,
        );
        $available = max(0, $total - $reserved - $rented);

        return $this->availabilityPayload(
            total: $total,
            reserved: $reserved,
            rented: $rented,
            available: $available,
            requested: $requested,
        );
    }

    private function serializedCapacity(Product $product, Branch $branch): int
    {
        return (int) DB::table('assets')
            ->where('product_id', $product->id)
            ->where('current_branch_id', $branch->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['maintenance', 'lost', 'retired', 'inactive'])
            ->whereNotIn('condition', ['lost', 'retired'])
            ->whereNotExists(function (QueryBuilder $maintenance): void {
                $maintenance
                    ->selectRaw('1')
                    ->from('maintenance_orders')
                    ->whereColumn('maintenance_orders.asset_id', 'assets.id')
                    ->whereNotIn('maintenance_orders.status', [
                        'completed',
                        'closed',
                        'cancelled',
                        'rejected',
                    ]);
            })
            ->count();
    }

    private function quantityCapacity(Product $product, Branch $branch): int
    {
        $inventory = DB::table('branch_inventories')
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->first([
                'quantity_on_hand',
                'quantity_maintenance',
            ]);

        if ($inventory === null) {
            return 0;
        }

        return max(
            0,
            (int) $inventory->quantity_on_hand
                - (int) $inventory->quantity_maintenance,
        );
    }

    private function bookingDemand(
        Product $product,
        Branch $branch,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): int {
        $direct = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.branch_id', $branch->id)
            ->where('booking_items.product_id', $product->id)
            ->whereNull('bookings.deleted_at')
            ->whereNotIn('bookings.status', self::NON_BLOCKING_BOOKING_STATUSES)
            ->where('bookings.starts_at', '<', $endsAt->toDateTimeString())
            ->where('bookings.ends_at', '>', $startsAt->toDateTimeString())
            ->sum('booking_items.quantity');
        $packages = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('package_items', 'package_items.package_id', '=', 'booking_items.package_id')
            ->where('bookings.branch_id', $branch->id)
            ->where('package_items.product_id', $product->id)
            ->where('package_items.is_optional', false)
            ->whereNull('bookings.deleted_at')
            ->whereNotIn('bookings.status', self::NON_BLOCKING_BOOKING_STATUSES)
            ->where('bookings.starts_at', '<', $endsAt->toDateTimeString())
            ->where('bookings.ends_at', '>', $startsAt->toDateTimeString())
            ->selectRaw(
                'COALESCE(SUM(booking_items.quantity * package_items.quantity), 0) AS aggregate',
            )
            ->value('aggregate');

        return max(0, (int) $direct + (int) $packages);
    }

    private function rentalDemand(
        Product $product,
        Branch $branch,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): int {
        $extensions = DB::table('rental_extensions')
            ->selectRaw('rental_id, MAX(extended_due_at) AS extended_due_at')
            ->whereIn('status', ['approved', 'completed'])
            ->groupBy('rental_id');
        $effectiveDueSql = <<<'SQL'
CASE
    WHEN rental_extensions_max.extended_due_at IS NULL
        OR rentals.due_at >= rental_extensions_max.extended_due_at
    THEN rentals.due_at
    ELSE rental_extensions_max.extended_due_at
END
SQL;

        $value = DB::table('rental_items')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->leftJoinSub(
                $extensions,
                'rental_extensions_max',
                'rental_extensions_max.rental_id',
                '=',
                'rentals.id',
            )
            ->where('rentals.branch_id', $branch->id)
            ->where('rental_items.product_id', $product->id)
            ->whereNull('rentals.deleted_at')
            ->whereNotIn('rentals.status', self::NON_BLOCKING_RENTAL_STATUSES)
            ->whereNotNull('rentals.checked_out_at')
            ->where('rentals.checked_out_at', '<', $endsAt->toDateTimeString())
            ->whereRaw("{$effectiveDueSql} > ?", [$startsAt->toDateTimeString()])
            ->selectRaw(<<<'SQL'
COALESCE(SUM(
    CASE
        WHEN rental_items.quantity > rental_items.returned_quantity
        THEN rental_items.quantity - rental_items.returned_quantity
        ELSE 0
    END
), 0) AS aggregate
SQL)
            ->value('aggregate');

        return max(0, (int) $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function availabilityPayload(
        int $total,
        int $reserved,
        int $rented,
        int $available,
        int $requested,
    ): array {
        if ($available >= $requested) {
            $status = 'available';
            $label = "Tersedia {$available} unit pada periode ini";
        } elseif ($available > 0) {
            $status = 'limited';
            $label = "Terbatas — hanya {$available} unit tersedia";
        } else {
            $status = 'unavailable';
            $label = 'Tidak tersedia pada periode ini';
        }

        return [
            'total_units' => $total,
            'reserved_units' => $reserved,
            'rented_units' => $rented,
            'available_units' => $available,
            'requested_units' => $requested,
            'status' => $status,
            'label' => $label,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productRate(
        Product $product,
        Branch $branch,
        CarbonImmutable $startsAt,
        ?int $rateId,
    ): ?array {
        $rates = ProductRate::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->where(function (Builder $scope) use ($branch): void {
                $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->where(function (Builder $dates) use ($startsAt): void {
                $dates
                    ->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', $startsAt->toDateString());
            })
            ->where(function (Builder $dates) use ($startsAt): void {
                $dates
                    ->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $startsAt->toDateString());
            })
            ->whereHas('ratePlan', fn (Builder $plan) => $plan->where('is_active', true))
            ->with('ratePlan')
            ->get();

        return $this->selectRate($rates, $branch, $rateId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packageRate(
        RentalPackage $package,
        Branch $branch,
        ?int $rateId,
    ): ?array {
        $rates = PackageRate::query()
            ->where('package_id', $package->id)
            ->where('is_active', true)
            ->where(function (Builder $scope) use ($branch): void {
                $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->whereHas('ratePlan', fn (Builder $plan) => $plan->where('is_active', true))
            ->with('ratePlan')
            ->get();

        return $this->selectRate($rates, $branch, $rateId);
    }

    /**
     * @param  EloquentCollection<int, ProductRate|PackageRate>  $rates
     * @return array<string, mixed>|null
     */
    private function selectRate(
        EloquentCollection $rates,
        Branch $branch,
        ?int $rateId,
    ): ?array {
        $selectedRates = $rates
            ->groupBy('rate_plan_id')
            ->map(fn (Collection $group) => $group
                ->sortByDesc(fn ($rate): int => (int) $rate->branch_id === $branch->id ? 1 : 0)
                ->first())
            ->filter()
            ->values();

        if ($rateId !== null) {
            $selected = $selectedRates->first(fn ($rate): bool => (int) $rate->id === $rateId);

            if ($selected === null) {
                throw ValidationException::withMessages([
                    'rate_id' => 'Tarif yang dipilih tidak tersedia untuk cabang ini.',
                ]);
            }
        } else {
            $selected = $selectedRates
                ->sortBy(fn ($rate): int => $this->durationMinutes(
                    (string) $rate->ratePlan->duration_unit,
                    (int) $rate->ratePlan->duration_value,
                ))
                ->first();
        }

        if ($selected === null || $selected->ratePlan === null) {
            return null;
        }

        $durationMinutes = $this->durationMinutes(
            (string) $selected->ratePlan->duration_unit,
            (int) $selected->ratePlan->duration_value,
        );

        return [
            'id' => $selected->id,
            'rate_plan' => $selected->ratePlan->name,
            'duration_label' => $this->durationLabel(
                (string) $selected->ratePlan->duration_unit,
                (int) $selected->ratePlan->duration_value,
            ),
            'duration_minutes' => $durationMinutes,
            'amount' => (float) $selected->amount,
            'deposit_amount' => (float) $selected->deposit_amount,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $rate
     * @return array<string, mixed>|null
     */
    private function estimate(
        ?array $rate,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $quantity,
    ): ?array {
        if ($rate === null) {
            return null;
        }

        $durationMinutes = max(1, (int) $startsAt->diffInMinutes($endsAt));
        $rateMinutes = max(1, (int) $rate['duration_minutes']);
        $billingUnits = (int) ceil($durationMinutes / $rateMinutes);
        $rentalAmount = (float) $rate['amount'] * $billingUnits * $quantity;
        $depositAmount = (float) $rate['deposit_amount'] * $quantity;

        return [
            'billing_units' => $billingUnits,
            'rental_amount' => $rentalAmount,
            'deposit_amount' => $depositAmount,
            'initial_payment_estimate' => $rentalAmount + $depositAmount,
            'note' => 'Estimasi belum termasuk diskon, denda, biaya tambahan, atau penyesuaian admin.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function period(CarbonImmutable $startsAt, CarbonImmutable $endsAt): array
    {
        $durationMinutes = max(1, (int) $startsAt->diffInMinutes($endsAt));

        return [
            'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
            'ends_at' => $endsAt->format('Y-m-d\\TH:i'),
            'starts_label' => $startsAt->locale('id')->translatedFormat('d M Y H:i'),
            'ends_label' => $endsAt->locale('id')->translatedFormat('d M Y H:i'),
            'duration_minutes' => $durationMinutes,
            'duration_label' => $this->humanDuration($durationMinutes),
            'timezone' => $startsAt->getTimezone()->getName(),
            'timezone_label' => $this->timezoneLabel($startsAt->getTimezone()->getName()),
        ];
    }

    /**
     * @param  array<string, mixed>  $period
     * @param  array<string, mixed>  $availability
     * @param  array<string, mixed>|null  $rate
     * @param  array<string, mixed>|null  $estimate
     */
    private function inquiryMessage(
        Branch $branch,
        string $itemType,
        string $itemName,
        int $quantity,
        array $period,
        array $availability,
        ?array $rate,
        ?array $estimate,
    ): string {
        $lines = [
            "Halo {$branch->name},",
            '',
            'Saya ingin menanyakan ketersediaan rental berikut:',
            "{$itemType}: {$itemName}",
            "Cabang: {$branch->name} ({$branch->code})",
            "Periode: {$period['starts_label']} – {$period['ends_label']} {$period['timezone_label']}",
            "Durasi: {$period['duration_label']}",
            "Jumlah: {$quantity} unit",
        ];

        if ($rate !== null) {
            $lines[] = "Tarif: {$rate['duration_label']} — {$this->rupiah((float) $rate['amount'])}";
        }

        if ($estimate !== null) {
            $lines[] = 'Estimasi biaya rental: '.$this->rupiah((float) $estimate['rental_amount']);

            if ((float) $estimate['deposit_amount'] > 0) {
                $lines[] = 'Estimasi deposit: '.$this->rupiah((float) $estimate['deposit_amount']);
            }
        }

        $lines[] = "Status sistem: {$availability['label']}";
        $lines[] = '';
        $lines[] = 'Mohon konfirmasi ketersediaan final dan proses booking. Terima kasih.';

        return implode("\n", $lines);
    }

    private function publicBranch(string $code): Branch
    {
        $branch = Branch::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->firstOrFail();
        $setting = DB::table('branch_settings')
            ->where('branch_id', $branch->id)
            ->where('key', 'public_catalog_enabled')
            ->where('is_public', true)
            ->value('value');
        $decoded = is_string($setting) ? json_decode($setting, true) : $setting;
        $enabled = is_bool($decoded)
            ? $decoded
            : in_array(mb_strtolower((string) $decoded), ['1', 'true', 'yes', 'on'], true);

        abort_unless($enabled, 404);

        return $branch;
    }

    /** @return array<string, mixed> */
    private function branchPayload(Branch $branch): array
    {
        return [
            'code' => $branch->code,
            'name' => $branch->name,
            'city' => $branch->city,
            'timezone' => $branch->timezone ?: 'Asia/Jakarta',
        ];
    }

    private function whatsappUrl(Branch $branch, string $message): ?string
    {
        $setting = DB::table('branch_settings')
            ->where('branch_id', $branch->id)
            ->where('key', 'public_whatsapp')
            ->where('is_public', true)
            ->value('value');
        $decoded = is_string($setting) ? json_decode($setting, true) : $setting;
        $number = (string) preg_replace(
            '/\D+/',
            '',
            (string) ($decoded ?: $branch->phone),
        );

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    private function timezoneLabel(string $timezone): string
    {
        return match ($timezone) {
            'Asia/Jakarta' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $timezone,
        };
    }

    private function durationMinutes(string $unit, int $value): int
    {
        return match ($unit) {
            'minute' => $value,
            'hour' => $value * 60,
            'day' => $value * 1440,
            'week' => $value * 10080,
            'month' => $value * 43200,
            default => $value,
        };
    }

    private function durationLabel(string $unit, int $value): string
    {
        $label = match ($unit) {
            'minute' => 'Menit',
            'hour' => 'Jam',
            'day' => 'Hari',
            'week' => 'Minggu',
            'month' => 'Bulan',
            default => ucfirst($unit),
        };

        return "{$value} {$label}";
    }

    private function humanDuration(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' hari';
        }

        if ($minutes % 60 === 0) {
            return ($minutes / 60).' jam';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes} menit";
        }

        return "{$hours} jam {$remainingMinutes} menit";
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
