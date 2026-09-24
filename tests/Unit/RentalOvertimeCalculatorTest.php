<?php

namespace Tests\Unit;

use App\Domain\Rentals\RentalOvertimeCalculator;
use App\Models\RentalItem;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class RentalOvertimeCalculatorTest extends TestCase
{
    public function test_partial_hour_is_rounded_up_and_hours_one_to_five_use_hourly_penalty(): void
    {
        $calculator = new RentalOvertimeCalculator();
        $dueAt = CarbonImmutable::parse('2026-09-23 10:00:00');
        $item = $this->item($dueAt, [
            'source' => 'booking_rate_snapshot',
            'grace_period_minutes' => 0,
            'hourly_penalty_amount' => 35000,
            'hourly_penalty_source' => 'late_fee_amount',
            'six_hour_amount' => 180000,
            'block_hours' => 6,
        ]);

        $result = $calculator->calculate($item, $dueAt->addHours(2)->addSecond());

        $this->assertSame(3, $result['billable_hours']);
        $this->assertSame(105000.0, $result['total_charge_amount']);
        $this->assertSame(0, $result['six_hour_blocks']);
    }

    public function test_six_hour_rate_is_used_as_a_block_and_repeats_for_later_blocks(): void
    {
        $calculator = new RentalOvertimeCalculator();
        $dueAt = CarbonImmutable::parse('2026-09-23 10:00:00');
        $item = $this->item($dueAt, [
            'source' => 'booking_rate_snapshot',
            'grace_period_minutes' => 0,
            'hourly_penalty_amount' => 35000,
            'hourly_penalty_source' => 'late_fee_amount',
            'six_hour_amount' => 180000,
            'block_hours' => 6,
        ]);

        $sixHours = $calculator->calculate($item, $dueAt->addHours(6));
        $fourteenHours = $calculator->calculate($item, $dueAt->addHours(14));

        $this->assertSame(180000.0, $sixHours['total_charge_amount']);
        $this->assertSame(1, $sixHours['six_hour_blocks']);
        $this->assertSame(0, $sixHours['remainder_hours']);
        $this->assertSame(430000.0, $fourteenHours['total_charge_amount']);
        $this->assertSame(2, $fourteenHours['six_hour_blocks']);
        $this->assertSame(2, $fourteenHours['remainder_hours']);
    }

    public function test_missing_six_hour_rate_falls_back_to_ten_percent_for_every_hour(): void
    {
        $calculator = new RentalOvertimeCalculator();
        $dueAt = CarbonImmutable::parse('2026-09-23 10:00:00');
        $item = new RentalItem([
            'id' => 10,
            'product_id' => 20,
            'unit_rate' => 350000,
            'due_at' => $dueAt,
        ]);

        $result = $calculator->calculate($item, $dueAt->addHours(7));

        $this->assertSame(7, $result['billable_hours']);
        $this->assertSame(245000.0, $result['total_charge_amount']);
        $this->assertSame('legacy_contract_fallback', $result['snapshot_source']);
    }

    public function test_quantity_multiplies_charge_without_changing_unit_policy(): void
    {
        $calculator = new RentalOvertimeCalculator();
        $dueAt = CarbonImmutable::parse('2026-09-23 10:00:00');
        $item = $this->item($dueAt, [
            'source' => 'booking_rate_snapshot',
            'grace_period_minutes' => 0,
            'hourly_penalty_amount' => 10000,
            'hourly_penalty_source' => 'late_fee_amount',
            'six_hour_amount' => null,
            'block_hours' => 6,
        ]);

        $result = $calculator->calculate($item, $dueAt->addHours(2), 4);

        $this->assertSame(20000.0, $result['unit_charge_amount']);
        $this->assertSame(80000.0, $result['total_charge_amount']);
    }

    /** @param array<string, mixed> $snapshot */
    private function item(CarbonImmutable $dueAt, array $snapshot): RentalItem
    {
        return new RentalItem([
            'id' => 10,
            'product_id' => 20,
            'unit_rate' => 350000,
            'due_at' => $dueAt,
            'overtime_snapshot' => [
                'version' => 1,
                'contract_unit_amount' => 350000,
                ...$snapshot,
            ],
        ]);
    }
}
