<?php

namespace App\Domain\Pricing;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\RentalExtension;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RentalPricingEngine
{
    public const MEMBER_DISCOUNT_PERCENT = 10.0;

    public function resolvePromotion(
        ?string $code,
        Branch $branch,
        Customer $customer,
        ?int $ignoreBookingId = null,
    ): ?Promotion {
        $normalized = mb_strtoupper(trim((string) $code));

        if ($normalized === '') {
            return null;
        }

        $promotion = Promotion::query()
            ->where('company_id', $branch->company_id)
            ->where('code', $normalized)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branch->id))
            ->orderByRaw('branch_id is null')
            ->lockForUpdate()
            ->first();

        if ($promotion === null) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Kode promo tidak ditemukan atau tidak berlaku untuk cabang ini.',
            ]);
        }

        if ($promotion->starts_at !== null && Carbon::parse((string) $promotion->starts_at)->isFuture()) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Promo belum memasuki periode aktif.',
            ]);
        }

        if ($promotion->ends_at !== null && Carbon::parse((string) $promotion->ends_at)->isPast()) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Promo sudah berakhir.',
            ]);
        }

        $rules = $this->rules($promotion);
        if (($rules['member_only'] ?? false) && ! $this->isEligibleMember($customer)) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Promo ini hanya berlaku untuk member Together.',
            ]);
        }
        if (($rules['non_member_only'] ?? false) && $this->isEligibleMember($customer)) {
            throw ValidationException::withMessages([
                'promotion_code' => 'Promo ini hanya berlaku untuk pelanggan non-member.',
            ]);
        }

        if ($promotion->usage_limit !== null) {
            $used = Booking::query()
                ->where('promotion_id', $promotion->id)
                ->whereNotIn('status', ['cancelled', 'expired'])
                ->when($ignoreBookingId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreBookingId))
                ->count();
            $used += RentalExtension::query()
                ->where('promotion_id', $promotion->id)
                ->whereIn('status', ['approved', 'completed'])
                ->count();

            if ($used >= $promotion->usage_limit) {
                throw ValidationException::withMessages([
                    'promotion_code' => 'Kuota penggunaan promo sudah habis.',
                ]);
            }
        }

        return $promotion;
    }

    /**
     * @return array{
     *     subtotal: float,
     *     member_discount: float,
     *     promotion_discount: float,
     *     discount_amount: float,
     *     total_amount: float,
     *     bonus_duration_units: int,
     *     effective_duration_units: int,
     *     strategy: 'none'|'membership'|'promotion'|'best_discount'|'stacked',
     *     snapshot: array<string, mixed>
     * }
     */
    public function price(
        float $subtotal,
        int $requestedDurationUnits,
        Customer $customer,
        RatePlan $plan,
        ?Promotion $promotion,
    ): array {
        $subtotal = round(max(0, $subtotal), 2);
        $requestedDurationUnits = max(1, $requestedDurationUnits);
        $memberEligible = $this->isEligibleMember($customer);
        $memberDiscount = $memberEligible
            ? round($subtotal * (self::MEMBER_DISCOUNT_PERCENT / 100), 2)
            : 0.0;
        $promotionDiscount = 0.0;
        $bonusDuration = 0;
        $allowMemberStack = false;

        if ($promotion !== null) {
            if ($subtotal + 0.009 < (float) $promotion->minimum_transaction) {
                throw ValidationException::withMessages([
                    'promotion_code' => sprintf(
                        'Promo membutuhkan minimum transaksi Rp %s.',
                        number_format((float) $promotion->minimum_transaction, 0, ',', '.'),
                    ),
                ]);
            }

            $promotionDiscount = match ($promotion->type) {
                'percentage' => round($subtotal * min(100, max(0, (float) $promotion->value)) / 100, 2),
                'fixed' => min($subtotal, round(max(0, (float) $promotion->value), 2)),
                default => 0.0,
            };

            if ($promotion->maximum_discount !== null && $promotionDiscount > 0) {
                $promotionDiscount = min($promotionDiscount, (float) $promotion->maximum_discount);
            }

            $bonusDuration = $promotion->type === 'bonus_duration'
                ? max(0, (int) $promotion->bonus_duration)
                : 0;
            $allowMemberStack = (bool) ($this->rules($promotion)['allow_member_stack'] ?? false);
        }

        if ($promotionDiscount > 0 && $memberDiscount > 0 && $allowMemberStack) {
            $discount = min($subtotal, $memberDiscount + $promotionDiscount);
            $strategy = 'stacked';
        } elseif ($promotionDiscount > 0 && $memberDiscount > 0) {
            $discount = max($memberDiscount, $promotionDiscount);
            $strategy = 'best_discount';
        } elseif ($promotionDiscount > 0) {
            $discount = $promotionDiscount;
            $strategy = 'promotion';
        } elseif ($memberDiscount > 0) {
            $discount = $memberDiscount;
            $strategy = 'membership';
        } else {
            $discount = 0.0;
            $strategy = 'none';
        }

        $discount = round(min($subtotal, max(0, $discount)), 2);
        $total = round($subtotal - $discount, 2);
        $effectiveDuration = $requestedDurationUnits + $bonusDuration;

        return [
            'subtotal' => $subtotal,
            'member_discount' => $memberDiscount,
            'promotion_discount' => $promotionDiscount,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'bonus_duration_units' => $bonusDuration,
            'effective_duration_units' => $effectiveDuration,
            'strategy' => $strategy,
            'snapshot' => [
                'version' => 1,
                'captured_at' => now()->toISOString(),
                'rate_plan' => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'duration_unit' => $plan->duration_unit,
                    'duration_value' => (int) $plan->duration_value,
                ],
                'requested_duration_units' => $requestedDurationUnits,
                'bonus_duration_units' => $bonusDuration,
                'effective_duration_units' => $effectiveDuration,
                'subtotal' => $subtotal,
                'membership' => [
                    'eligible' => $memberEligible,
                    'percent' => $memberEligible ? self::MEMBER_DISCOUNT_PERCENT : 0.0,
                    'discount_amount' => $memberDiscount,
                ],
                'promotion' => $promotion === null ? null : [
                    'id' => $promotion->id,
                    'code' => $promotion->code,
                    'name' => $promotion->name,
                    'type' => $promotion->type,
                    'value' => (float) $promotion->value,
                    'maximum_discount' => $promotion->maximum_discount === null
                        ? null
                        : (float) $promotion->maximum_discount,
                    'minimum_transaction' => (float) $promotion->minimum_transaction,
                    'bonus_duration' => (int) $promotion->bonus_duration,
                    'allow_member_stack' => $allowMemberStack,
                    'discount_amount' => $promotionDiscount,
                ],
                'discount_strategy' => $strategy,
                'discount_amount' => $discount,
                'total_amount' => $total,
            ],
        ];
    }

    public function bonusDurationUnits(?Promotion $promotion): int
    {
        if ($promotion === null || $promotion->type !== 'bonus_duration') {
            return 0;
        }

        return max(0, (int) $promotion->bonus_duration);
    }

    public function isEligibleMember(Customer $customer): bool
    {
        if (! $customer->is_member) {
            return false;
        }

        return $customer->member_since === null
            || ! Carbon::parse((string) $customer->member_since)->isFuture();
    }

    /** @return array<string, bool|int|float|string|null> */
    private function rules(Promotion $promotion): array
    {
        $rules = $promotion->getAttribute('rules');

        if (! is_array($rules)) {
            return [];
        }

        /** @var array<string, bool|int|float|string|null> $rules */
        return $rules;
    }
}
