<?php

namespace App\Domain\Rentals;

use App\Domain\Bookings\BookingManager;
use App\Domain\Finance\PaymentManager;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetReservation;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly BookingManager $bookings,
        private readonly PaymentManager $payments,
        private readonly RentalCollateralManager $collaterals,
    ) {}

    /** @param array<string, mixed> $data */
    public function checkout(Booking $booking, array $data, User $actor): Rental
    {
        return DB::transaction(
            fn (): Rental => $this->checkoutLocked($booking->id, $data, $actor),
            3,
        );
    }

    /** @param array<string, mixed> $data */
    public function createDirect(array $data, User $actor): Rental
    {
        return DB::transaction(function () use ($data, $actor): Rental {
            $booking = $this->bookings->create([
                ...$data,
                'source' => 'direct',
            ], $actor);
            $this->bookings->confirm($booking, $actor);

            return $this->checkoutLocked($booking->id, $data, $actor);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function checkoutLocked(int $bookingId, array $data, User $actor): Rental
    {
        $booking = Booking::query()
            ->with(['branch', 'items', 'reservations.asset.product'])
            ->lockForUpdate()
            ->findOrFail($bookingId);

        if ($booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'booking' => 'Checkout hanya dapat dilakukan dari booking terkonfirmasi.',
            ]);
        }

        if ($booking->rental()->exists()) {
            throw ValidationException::withMessages([
                'booking' => 'Booking ini sudah dikonversi menjadi rental.',
            ]);
        }

        $reservations = AssetReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'reserved')
            ->with(['asset.product', 'bookingItem'])
            ->lockForUpdate()
            ->get();

        $expectedUnits = (int) $booking->items->sum('quantity');

        if ($reservations->isEmpty() || $reservations->count() < $expectedUnits) {
            throw ValidationException::withMessages([
                'booking' => 'Reservasi unit booking tidak lengkap. Perbarui booking sebelum checkout.',
            ]);
        }

        $invalidAsset = $reservations->first(
            fn (AssetReservation $reservation): bool => ! $reservation->asset->is_active
                || $reservation->asset->status !== 'available'
                || $reservation->asset->current_branch_id !== $booking->branch_id
                || in_array($reservation->asset->condition, ['damaged', 'lost'], true),
        );

        if ($invalidAsset !== null) {
            throw ValidationException::withMessages([
                'booking' => "Unit {$invalidAsset->asset->asset_code} tidak siap untuk checkout.",
            ]);
        }

        $checkedOutAt = $data['checked_out_at'] ?? now();
        $rentalPayment = (float) ($data['payment_amount'] ?? 0);
        $depositPaid = (float) ($data['deposit_paid'] ?? 0);
        $bookingRentalPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'rental')
            ->sum('amount');
        $bookingDepositPaid = (float) $booking->payments()
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->sum('amount');
        $remainingRental = max(0, (float) $booking->total_amount - $bookingRentalPaid);
        $remainingDeposit = max(0, (float) $booking->deposit_required - $bookingDepositPaid);

        if ($rentalPayment > $remainingRental) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Pembayaran rental tidak boleh melebihi sisa tagihan.',
            ]);
        }

        if ($depositPaid > $remainingDeposit) {
            throw ValidationException::withMessages([
                'deposit_paid' => 'Deposit jaminan tidak boleh melebihi kekurangan deposit.',
            ]);
        }

        $totalRentalPaid = $bookingRentalPaid + $rentalPayment;
        $totalDepositPaid = $bookingDepositPaid + $depositPaid;
        $rental = Rental::query()->create([
            'branch_id' => $booking->branch_id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'checked_out_by_employee_id' => $actor->employee?->id,
            'rate_plan_id' => $booking->rate_plan_id,
            'promotion_id' => $booking->promotion_id,
            'rental_number' => $this->numbers->nextRental($booking->branch),
            'status' => 'active',
            'checked_out_at' => $checkedOutAt,
            'due_at' => $booking->ends_at,
            'subtotal' => $booking->subtotal,
            'booking_payment_amount' => $bookingRentalPaid,
            'deposit_amount' => $totalDepositPaid,
            'discount_amount' => $booking->discount_amount,
            'tax_amount' => $booking->tax_amount,
            'total_amount' => $booking->total_amount,
            'paid_amount' => $totalRentalPaid,
            'balance_due' => max(0, (float) $booking->total_amount - $totalRentalPaid),
            'pricing_snapshot' => $booking->pricing_snapshot,
            'notes' => $data['checkout_notes'] ?? $booking->notes,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->createRentalItems($rental, $reservations, $data, $actor);
        $rawCollaterals = $data['collaterals'] ?? [];
        /** @var list<array<string, mixed>> $collateralInputs */
        $collateralInputs = [];
        if (is_array($rawCollaterals)) {
            foreach ($rawCollaterals as $input) {
                if (is_array($input)) {
                    $collateralInputs[] = $input;
                }
            }
        }
        $this->collaterals->receiveManyLocked(
            $rental,
            $collateralInputs,
            $actor,
            $checkedOutAt,
        );
        Payment::query()
            ->where('booking_id', $booking->id)
            ->whereNull('rental_id')
            ->where('status', 'completed')
            ->update(['rental_id' => $rental->id, 'updated_at' => now()]);
        $this->recordPayments($rental, $data, $actor);

        $reservations->each(function (AssetReservation $reservation) use ($actor): void {
            $reservation->update([
                'status' => 'converted',
                'released_at' => now(),
                'released_by' => $actor->id,
                'release_reason' => 'Dikonversi ke rental aktif.',
            ]);
        });
        Asset::query()->whereKey($reservations->pluck('asset_id'))->update([
            'status' => 'rented',
            'updated_at' => now(),
        ]);
        $booking->update(['status' => 'converted', 'updated_by' => $actor->id]);
        $booking->statusHistories()->create([
            'from_status' => 'confirmed',
            'to_status' => 'converted',
            'reason' => "Checkout ke rental {$rental->rental_number}.",
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
        $rental->statusHistories()->create([
            'from_status' => null,
            'to_status' => 'active',
            'reason' => $booking->source === 'direct'
                ? 'Rental In Store berhasil di-checkout.'
                : "Checkout dari booking {$booking->booking_number}.",
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);

        return $rental->fresh([
            'items.assets.asset',
            'booking',
            'customer',
            'payments',
            'collaterals.receiver:id,name',
        ]);
    }

    /**
     * @param  Collection<int, AssetReservation>  $reservations
     * @param  array<string, mixed>  $data
     */
    private function createRentalItems(
        Rental $rental,
        Collection $reservations,
        array $data,
        User $actor,
    ): void {
        $rawAssetInputs = $data['assets'] ?? [];
        /** @var list<array<string, mixed>> $assetInputs */
        $assetInputs = [];
        if (is_array($rawAssetInputs)) {
            foreach ($rawAssetInputs as $input) {
                if (is_array($input)) {
                    $assetInputs[] = $input;
                }
            }
        }
        $conditions = collect($assetInputs)
            ->keyBy(fn (array $input): int => (int) ($input['asset_id'] ?? 0));

        foreach ($reservations->groupBy(
            fn (AssetReservation $item): string => $item->booking_item_id
                .':'
                .$item->asset->product_id,
        ) as $group) {
            /** @var AssetReservation $first */
            $first = $group->first();
            $bookingItem = $first->bookingItem;
            $bookingItemUnitCount = max(1, $reservations
                ->where('booking_item_id', $bookingItem->id)
                ->count());
            $allocatedTotal = round(
                (float) $bookingItem->total_amount * $group->count() / $bookingItemUnitCount,
                2,
            );
            $item = RentalItem::query()->create([
                'rental_id' => $rental->id,
                'booking_item_id' => $bookingItem->id,
                'product_id' => $first->asset->product_id,
                'description' => $bookingItem->package_id === null
                    ? $bookingItem->description
                    : "{$bookingItem->description} · {$first->asset->product->name}",
                'quantity' => $group->count(),
                'unit_rate' => $allocatedTotal / max(1, $group->count()),
                'total_amount' => $allocatedTotal,
                'due_at' => $rental->due_at,
                'status' => 'out',
            ]);

            foreach ($group as $reservation) {
                $condition = $conditions->get($reservation->asset_id);
                $conditionValue = $condition['condition'] ??
                    $data['checkout_condition'] ??
                    $reservation->asset->condition;
                $notes = $condition['notes'] ?? null;
                $item->assets()->create([
                    'asset_id' => $reservation->asset_id,
                    'checkout_condition' => $conditionValue,
                    'checked_out_at' => $rental->checked_out_at,
                    'status' => 'out',
                    'notes' => $notes,
                ]);
                AssetInspection::query()->create([
                    'branch_id' => $rental->branch_id,
                    'asset_id' => $reservation->asset_id,
                    'rental_item_id' => $item->id,
                    'type' => 'checkout',
                    'condition' => $conditionValue,
                    'notes' => $notes,
                    'inspected_by' => $actor->id,
                    'inspected_at' => $rental->checked_out_at,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function recordPayments(Rental $rental, array $data, User $actor): void
    {
        $methodId = $data['payment_method_id'] ?? null;

        if ($methodId === null) {
            return;
        }

        foreach ([
            [
                'amount' => (float) ($data['payment_amount'] ?? 0),
                'type' => 'rental',
                'category' => 'RENTAL',
            ],
            [
                'amount' => (float) ($data['deposit_paid'] ?? 0),
                'type' => 'deposit',
                'category' => 'DEPOSIT',
            ],
        ] as $entry) {
            if ($entry['amount'] <= 0) {
                continue;
            }

            $this->payments->record($rental->branch, [
                'customer_id' => $rental->customer_id,
                'booking_id' => $rental->booking_id,
                'rental_id' => $rental->id,
                'payment_method_id' => $methodId,
                'financial_category_code' => $entry['category'],
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'direction' => 'in',
                'type' => $entry['type'],
                'source_context' => 'rental_checkout',
                'amount' => $entry['amount'],
                'paid_at' => $rental->checked_out_at,
                'external_reference' => $data['payment_reference'] ?? null,
                'notes' => $data['payment_notes'] ?? null,
            ], $actor);
        }
    }
}
