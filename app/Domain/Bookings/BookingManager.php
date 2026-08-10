<?php

namespace App\Domain\Bookings;

use App\Domain\Finance\PaymentManager;
use App\Domain\Pricing\RentalPricingEngine;
use App\Models\Asset;
use App\Models\AssetReservation;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PackageRate;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingManager
{
    public function __construct(
        private readonly BookingNumberGenerator $numbers,
        private readonly PaymentManager $payments,
        private readonly RentalPricingEngine $pricing,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($data, $actor): Booking {
            $branch = Branch::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($data['branch_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $customer = Customer::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $promotion = $this->pricing->resolvePromotion(
                $data['promotion_code'] ?? null,
                $branch,
                $customer,
            );
            $requestedDuration = max(1, (int) $data['duration_units']);
            $effectiveDuration = $requestedDuration + $this->pricing->bonusDurationUnits($promotion);
            [$plan, $startsAt, $endsAt] = $this->resolvePeriod(
                $data,
                $actor,
                $branch,
                $effectiveDuration,
            );

            $booking = Booking::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'handled_by_employee_id' => $actor->employee?->id,
                'rate_plan_id' => $plan->id,
                'promotion_id' => $promotion?->id,
                'booking_number' => $this->numbers->next($branch),
                'status' => 'draft',
                'source' => $data['source'],
                'booked_at' => now(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->replaceItems($booking, $data, $plan, $customer, $promotion, $requestedDuration);
            $this->recordInitialPayments($booking, $data, $actor);
            $this->history($booking, null, 'draft', 'Booking dibuat.', $actor);

            return $booking->fresh(['items', 'reservations']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function recordInitialPayments(Booking $booking, array $data, User $actor): void
    {
        if ($booking->source === 'direct') {
            return;
        }

        $this->recordPayments($booking, $data, $actor);
    }

    /** @param array<string, mixed> $data */
    private function recordPayments(Booking $booking, array $data, User $actor): void
    {
        $rentalAmount = (float) ($data['payment_amount'] ?? 0);
        $depositAmount = (float) ($data['deposit_paid'] ?? 0);
        $rentalPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'rental')
            ->sum('amount');
        $depositPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->sum('amount');

        if ($rentalPaid + $rentalAmount > (float) $booking->total_amount) {
            throw ValidationException::withMessages([
                'payment_amount' => 'DP sewa tidak boleh melebihi sisa tagihan.',
            ]);
        }

        if ($depositPaid + $depositAmount > (float) $booking->deposit_required) {
            throw ValidationException::withMessages([
                'deposit_paid' => 'Deposit jaminan tidak boleh melebihi kekurangan deposit.',
            ]);
        }

        $methodId = $data['payment_method_id'] ?? null;

        if ($methodId === null || ($rentalAmount <= 0 && $depositAmount <= 0)) {
            return;
        }

        foreach ([
            ['amount' => $rentalAmount, 'type' => 'rental', 'category' => 'RENTAL'],
            ['amount' => $depositAmount, 'type' => 'deposit', 'category' => 'DEPOSIT'],
        ] as $entry) {
            if ($entry['amount'] <= 0) {
                continue;
            }

            $this->payments->record($booking->branch, [
                'customer_id' => $booking->customer_id,
                'booking_id' => $booking->id,
                'payment_method_id' => $methodId,
                'financial_category_code' => $entry['category'],
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'direction' => 'in',
                'type' => $entry['type'],
                'source_context' => 'booking',
                'amount' => $entry['amount'],
                'paid_at' => now(),
                'external_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['payment_notes']
                    ?? 'Pembayaran diterima pada booking.',
            ], $actor);
        }

        $booking->update(['deposit_paid' => $depositPaid + $depositAmount]);
    }

    /** @param array<string, mixed> $data */
    public function update(Booking $booking, array $data, User $actor): Booking
    {
        if ($booking->status !== 'draft') {
            throw ValidationException::withMessages([
                'booking' => 'Hanya booking draft yang dapat diubah.',
            ]);
        }

        return DB::transaction(function () use ($booking, $data, $actor): Booking {
            $locked = Booking::query()->with('branch')->lockForUpdate()->findOrFail($booking->id);
            $customer = Customer::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $promotion = $this->pricing->resolvePromotion(
                $data['promotion_code'] ?? null,
                $locked->branch,
                $customer,
                $locked->id,
            );
            $requestedDuration = max(1, (int) $data['duration_units']);
            $effectiveDuration = $requestedDuration + $this->pricing->bonusDurationUnits($promotion);
            [$plan, $startsAt, $endsAt] = $this->resolvePeriod(
                $data,
                $actor,
                $locked->branch,
                $effectiveDuration,
            );

            $locked->update([
                'customer_id' => $customer->id,
                'rate_plan_id' => $plan->id,
                'promotion_id' => $promotion?->id,
                'source' => $data['source'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);
            $locked->reservations()->delete();
            $locked->items()->delete();
            $this->replaceItems($locked, $data, $plan, $customer, $promotion, $requestedDuration);
            $this->assertExistingPaymentsFitPricing($locked);

            return $locked->fresh(['items', 'reservations', 'promotion']);
        }, 3);
    }

    public function confirm(Booking $booking, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $actor): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages([
                    'booking' => 'Booking hanya dapat dikonfirmasi dari status draft.',
                ]);
            }

            $this->assertReservationsStillAvailable($locked);
            $locked->update(['status' => 'confirmed', 'updated_by' => $actor->id]);
            $this->history($locked, 'draft', 'confirmed', 'Booking dikonfirmasi.', $actor);

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function receivePayment(Booking $booking, array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $data, $actor): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Pembayaran hanya dapat dicatat pada booking aktif.',
                ]);
            }

            $this->recordPayments($locked, $data, $actor);

            return $locked->fresh(['payments.paymentMethod']);
        }, 3);
    }

    public function cancel(Booking $booking, string $reason, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $reason, $actor): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($locked->status, Booking::ACTIVE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Booking pada status ini tidak dapat dibatalkan.',
                ]);
            }

            $from = $locked->status;
            $locked->reservations()->where('status', 'reserved')->update([
                'status' => 'released',
                'released_at' => now(),
                'released_by' => $actor->id,
                'release_reason' => $reason,
                'updated_at' => now(),
            ]);
            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
                'updated_by' => $actor->id,
            ]);
            $this->history($locked, $from, 'cancelled', $reason, $actor);

            return $locked;
        }, 3);
    }

    /** @return array{available: bool, required: int, available_count: int, ends_at: string} */
    public function availability(
        int $branchId,
        int $productId,
        string $startsAt,
        int $ratePlanId,
        int $durationUnits,
        int $quantity,
        ?int $ignoreBookingId = null,
    ): array {
        $plan = RatePlan::query()
            ->where('is_active', true)
            ->whereKey($ratePlanId)
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branchId))
            ->firstOrFail();
        $startsAtValue = CarbonImmutable::parse($startsAt);
        $endsAt = $this->calculateEndsAt($startsAtValue, $plan, $durationUnits);
        $count = $this->availableAssetsQuery(
            $branchId,
            $productId,
            $startsAtValue,
            $endsAt,
            $ignoreBookingId,
        )->count();

        return [
            'available' => $count >= $quantity,
            'required' => $quantity,
            'available_count' => $count,
            'ends_at' => $endsAt->toDateTimeString(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function replaceItems(
        Booking $booking,
        array $data,
        RatePlan $plan,
        Customer $customer,
        ?Promotion $promotion,
        int $requestedDuration,
    ): void {
        $units = $requestedDuration;
        $subtotal = 0.0;
        $deposit = 0.0;

        foreach ($data['items'] as $position => $payload) {
            [$description, $rate, $depositRate, $requirements] = $payload['type'] === 'product'
                ? $this->productPricing($booking, $plan, (int) $payload['id'], (int) $payload['quantity'])
                : $this->packagePricing($booking, $plan, (int) $payload['id'], (int) $payload['quantity']);
            $lineTotal = $rate * $units * (int) $payload['quantity'];
            $item = $booking->items()->create([
                'product_id' => $payload['type'] === 'product' ? $payload['id'] : null,
                'package_id' => $payload['type'] === 'package' ? $payload['id'] : null,
                'description' => $description,
                'quantity' => $payload['quantity'],
                'unit_rate' => $rate,
                'additional_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $lineTotal,
            ]);
            $this->reserveRequirements($booking, $item, $requirements, $position);
            $subtotal += $lineTotal;
            $deposit += $depositRate * (int) $payload['quantity'];
        }

        $pricing = $this->pricing->price(
            $subtotal,
            $requestedDuration,
            $customer,
            $plan,
            $promotion,
        );

        $booking->update([
            'promotion_id' => $promotion?->id,
            'subtotal' => $pricing['subtotal'],
            'discount_amount' => $pricing['discount_amount'],
            'tax_amount' => 0,
            'total_amount' => $pricing['total_amount'],
            'deposit_required' => $deposit,
            'pricing_snapshot' => $pricing['snapshot'],
        ]);
    }

    /** @return array{string, float, float, Collection<int, array{product_id: int, quantity: int}>} */
    private function productPricing(Booking $booking, RatePlan $plan, int $productId, int $quantity): array
    {
        $branch = $booking->branch;

        $product = Product::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)->where('is_rentable', true)->findOrFail($productId);
        $rate = ProductRate::query()
            ->where('product_id', $product->id)->where('rate_plan_id', $plan->id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('branch_id', $booking->branch_id)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id is null')->first();

        if ($rate === null) {
            throw ValidationException::withMessages(['items' => "Tarif {$product->name} tidak tersedia untuk cabang dan rate plan terpilih."]);
        }

        return [$product->name, (float) $rate->amount, (float) $rate->deposit_amount, collect([
            ['product_id' => $product->id, 'quantity' => $quantity],
        ])];
    }

    /** @return array{string, float, float, Collection<int, array{product_id: int, quantity: int}>} */
    private function packagePricing(Booking $booking, RatePlan $plan, int $packageId, int $quantity): array
    {
        $branch = $booking->branch;

        $package = RentalPackage::query()
            ->where('company_id', $branch->company_id)->where('is_active', true)
            ->with(['items' => fn ($query) => $query->where('is_optional', false)])
            ->findOrFail($packageId);
        $rate = PackageRate::query()
            ->where('package_id', $package->id)->where('rate_plan_id', $plan->id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('branch_id', $booking->branch_id)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id is null')->first();

        if ($rate === null) {
            throw ValidationException::withMessages(['items' => "Tarif paket {$package->name} tidak tersedia."]);
        }

        $requirements = $package->items->map(fn ($item): array => [
            'product_id' => (int) $item->product_id,
            'quantity' => (int) $item->quantity * $quantity,
        ]);

        return [$package->name, (float) $rate->amount, (float) $rate->deposit_amount, $requirements];
    }

    /** @param Collection<int, array{product_id: int, quantity: int}> $requirements */
    private function reserveRequirements(Booking $booking, BookingItem $item, Collection $requirements, int $position): void
    {
        foreach ($requirements as $requirement) {
            $assets = $this->availableAssetsQuery(
                $booking->branch_id,
                $requirement['product_id'],
                CarbonImmutable::parse($booking->starts_at),
                CarbonImmutable::parse($booking->ends_at),
                null,
            )->lockForUpdate()->limit($requirement['quantity'])->get();

            if ($assets->count() < $requirement['quantity']) {
                throw ValidationException::withMessages([
                    "items.{$position}.quantity" => 'Unit tersedia tidak mencukupi pada periode yang dipilih.',
                ]);
            }

            foreach ($assets as $asset) {
                AssetReservation::query()->create([
                    'branch_id' => $booking->branch_id,
                    'booking_id' => $booking->id,
                    'booking_item_id' => $item->id,
                    'asset_id' => $asset->id,
                    'starts_at' => $booking->starts_at,
                    'ends_at' => $booking->ends_at,
                    'status' => 'reserved',
                ]);
            }
        }
    }

    /** @return Builder<Asset> */
    private function availableAssetsQuery(
        int $branchId,
        int $productId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $ignoreBookingId,
    ): Builder {
        return Asset::query()
            ->where('current_branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('status', 'available')
            ->whereNotIn('condition', ['damaged', 'lost'])
            ->whereDoesntHave('reservations', function (Builder $query) use ($startsAt, $endsAt, $ignoreBookingId): void {
                $query->where('status', 'reserved')
                    ->when($ignoreBookingId !== null, fn (Builder $reservation) => $reservation->where('booking_id', '!=', $ignoreBookingId))
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
            })
            ->orderBy('id');
    }

    private function assertReservationsStillAvailable(Booking $booking): void
    {
        $conflict = AssetReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'reserved')
            ->whereHas('asset', fn (Builder $query) => $query
                ->where('status', '!=', 'available')
                ->orWhere('is_active', false)
                ->orWhere('current_branch_id', '!=', $booking->branch_id))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['booking' => 'Satu atau lebih aset tidak lagi tersedia. Perbarui booking terlebih dahulu.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{RatePlan, CarbonImmutable, CarbonImmutable}
     */
    private function resolvePeriod(
        array $data,
        User $actor,
        Branch $branch,
        ?int $durationUnits = null,
    ): array {
        $plan = RatePlan::query()
            ->where('company_id', $actor->company_id)
            ->where('is_active', true)
            ->whereKey($data['rate_plan_id'])
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->id))
            ->firstOrFail();
        $startsAt = CarbonImmutable::parse($data['starts_at']);

        return [
            $plan,
            $startsAt,
            $this->calculateEndsAt(
                $startsAt,
                $plan,
                $durationUnits ?? (int) $data['duration_units'],
            ),
        ];
    }

    private function calculateEndsAt(
        CarbonImmutable $startsAt,
        RatePlan $plan,
        int $durationUnits,
    ): CarbonImmutable {
        $unitMinutes = match ($plan->duration_unit) {
            'hour' => 60,
            'week' => 10_080,
            'month' => 43_200,
            default => 1_440,
        };

        return $startsAt->addMinutes($plan->duration_value * $durationUnits * $unitMinutes);
    }

    private function assertExistingPaymentsFitPricing(Booking $booking): void
    {
        $rentalPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'rental')
            ->sum('amount');
        $depositPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->sum('amount');

        if ($rentalPaid > (float) $booking->total_amount + 0.009) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Perubahan harga membuat pembayaran sewa yang sudah masuk melebihi total booking.',
            ]);
        }

        if ($depositPaid > (float) $booking->deposit_required + 0.009) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Perubahan harga membuat deposit yang sudah masuk melebihi deposit wajib.',
            ]);
        }
    }

    private function history(Booking $booking, ?string $from, string $to, ?string $reason, User $actor): void
    {
        $booking->statusHistories()->create([
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
            'changed_by' => $actor->id, 'changed_at' => now(),
        ]);
    }
}
