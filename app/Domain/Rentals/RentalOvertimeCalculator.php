<?php

namespace App\Domain\Rentals;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\RentalItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class RentalOvertimeCalculator
{
    public const FALLBACK_PERCENT = 10.0;

    public const HOURLY_WINDOW_HOURS = 5;

    public const BLOCK_HOURS = 6;

    /**
     * Capture the overtime terms while the booking contract is created.
     * additional_hour_amount is retained for audit only; return overtime never stacks it
     * with late_fee_amount.
     *
     * @return array<string, mixed>
     */
    public function snapshotForBookingProduct(Branch $branch, RatePlan $plan, Product $product): array
    {
        $sourceRate = $this->rateFor($product->id, $branch->id, $plan->id);
        $sixHourPlan = RatePlan::query()
            ->where('company_id', $branch->company_id)
            ->where('duration_unit', 'hour')
            ->where('duration_value', self::BLOCK_HOURS)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('branch_id', $branch->id)
                ->orWhereNull('branch_id'))
            ->orderByRaw('branch_id is null')
            ->first();
        $sixHourRate = $sixHourPlan === null
            ? null
            : $this->rateFor($product->id, $branch->id, $sixHourPlan->id);

        return [
            'version' => 1,
            'captured_at' => now()->toISOString(),
            'source' => 'booking_rate_snapshot',
            'product_id' => $product->id,
            'source_rate_plan_id' => $plan->id,
            'source_product_rate_id' => $sourceRate?->id,
            'source_rate_amount' => $sourceRate === null ? null : (float) $sourceRate->amount,
            'late_fee_amount' => $sourceRate === null ? null : (float) $sourceRate->late_fee_amount,
            'additional_hour_amount' => $sourceRate === null ? null : (float) $sourceRate->additional_hour_amount,
            'six_hour_rate_plan_id' => $sixHourPlan?->id,
            'six_hour_product_rate_id' => $sixHourRate?->id,
            'six_hour_amount' => $sixHourRate === null ? null : (float) $sixHourRate->amount,
            'grace_period_minutes' => (int) $plan->grace_period_minutes,
            'fallback_percent' => self::FALLBACK_PERCENT,
            'hourly_window_hours' => self::HOURLY_WINDOW_HOURS,
            'block_hours' => self::BLOCK_HOURS,
            'partial_hour_rounding' => 'ceil',
        ];
    }

    /** @param array<string, mixed>|null $snapshot
     *  @return array<string, mixed>
     */
    public function finalizeRentalItemSnapshot(?array $snapshot, float $contractUnitAmount): array
    {
        $snapshot ??= [
            'version' => 1,
            'captured_at' => now()->toISOString(),
            'source' => 'legacy_contract_fallback',
            'late_fee_amount' => null,
            'additional_hour_amount' => null,
            'six_hour_amount' => null,
            'grace_period_minutes' => 0,
            'fallback_percent' => self::FALLBACK_PERCENT,
            'hourly_window_hours' => self::HOURLY_WINDOW_HOURS,
            'block_hours' => self::BLOCK_HOURS,
            'partial_hour_rounding' => 'ceil',
        ];

        $explicitLateFee = max(0, (float) ($snapshot['late_fee_amount'] ?? 0));
        $fallbackPercent = max(0, (float) ($snapshot['fallback_percent'] ?? self::FALLBACK_PERCENT));
        $hourlyAmount = $explicitLateFee > 0
            ? $explicitLateFee
            : round(max(0, $contractUnitAmount) * $fallbackPercent / 100, 2);

        return [
            ...$snapshot,
            'contract_unit_amount' => round(max(0, $contractUnitAmount), 2),
            'hourly_penalty_amount' => $hourlyAmount,
            'hourly_penalty_source' => $explicitLateFee > 0
                ? 'late_fee_amount'
                : 'contract_fallback_percent',
        ];
    }

    /** @return array<string, mixed> */
    public function calculate(RentalItem $item, CarbonImmutable $returnedAt, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $dueAt = CarbonImmutable::parse((string) ($item->due_at ?? $item->rental?->due_at));
        $snapshot = is_array($item->overtime_snapshot)
            ? $item->overtime_snapshot
            : $this->finalizeRentalItemSnapshot(null, (float) $item->unit_rate);
        $graceMinutes = max(0, (int) ($snapshot['grace_period_minutes'] ?? 0));
        $effectiveDueAt = $dueAt->addMinutes($graceMinutes);
        $hourlyAmount = max(0, (float) ($snapshot['hourly_penalty_amount']
            ?? round((float) $item->unit_rate * self::FALLBACK_PERCENT / 100, 2)));
        $sixHourAmount = isset($snapshot['six_hour_amount'])
            ? max(0, (float) $snapshot['six_hour_amount'])
            : null;

        if (! $returnedAt->isAfter($effectiveDueAt)) {
            return $this->breakdown(
                $item,
                $returnedAt,
                $dueAt,
                $effectiveDueAt,
                $quantity,
                0,
                $hourlyAmount,
                $sixHourAmount,
                0,
                0,
                0.0,
                $snapshot,
            );
        }

        $overdueSeconds = $effectiveDueAt->diffInSeconds($returnedAt);
        $billableHours = (int) ceil($overdueSeconds / 3600);
        $blockHours = max(1, (int) ($snapshot['block_hours'] ?? self::BLOCK_HOURS));
        $blocks = 0;
        $remainderHours = $billableHours;

        if ($sixHourAmount !== null && $sixHourAmount > 0 && $billableHours >= $blockHours) {
            $blocks = intdiv($billableHours, $blockHours);
            $remainderHours = $billableHours % $blockHours;
            $unitCharge = ($blocks * $sixHourAmount) + ($remainderHours * $hourlyAmount);
        } else {
            $unitCharge = $billableHours * $hourlyAmount;
        }

        return $this->breakdown(
            $item,
            $returnedAt,
            $dueAt,
            $effectiveDueAt,
            $quantity,
            $billableHours,
            $hourlyAmount,
            $sixHourAmount,
            $blocks,
            $remainderHours,
            round($unitCharge, 2),
            $snapshot,
        );
    }

    /** @return array<string, mixed> */
    private function breakdown(
        RentalItem $item,
        CarbonImmutable $returnedAt,
        CarbonImmutable $dueAt,
        CarbonImmutable $effectiveDueAt,
        int $quantity,
        int $billableHours,
        float $hourlyAmount,
        ?float $sixHourAmount,
        int $blocks,
        int $remainderHours,
        float $unitCharge,
        array $snapshot,
    ): array {
        return [
            'version' => 1,
            'rental_item_id' => $item->id,
            'product_id' => $item->product_id,
            'due_at' => $dueAt->toISOString(),
            'effective_due_at' => $effectiveDueAt->toISOString(),
            'returned_at' => $returnedAt->toISOString(),
            'quantity' => $quantity,
            'billable_hours' => $billableHours,
            'partial_hour_rounding' => 'ceil',
            'hourly_penalty_amount' => round($hourlyAmount, 2),
            'hourly_penalty_source' => $snapshot['hourly_penalty_source'] ?? 'contract_fallback_percent',
            'six_hour_amount' => $sixHourAmount === null ? null : round($sixHourAmount, 2),
            'six_hour_blocks' => $blocks,
            'remainder_hours' => $remainderHours,
            'unit_charge_amount' => round($unitCharge, 2),
            'total_charge_amount' => round($unitCharge * $quantity, 2),
            'snapshot_source' => $snapshot['source'] ?? 'legacy_contract_fallback',
        ];
    }

    private function rateFor(int $productId, int $branchId, int $ratePlanId): ?ProductRate
    {
        return ProductRate::query()
            ->where('product_id', $productId)
            ->where('rate_plan_id', $ratePlanId)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id'))
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_from')
                ->orWhereDate('valid_from', '<=', today()))
            ->where(fn (Builder $query) => $query
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', today()))
            ->orderByRaw('branch_id is null')
            ->latest('valid_from')
            ->first();
    }
}
