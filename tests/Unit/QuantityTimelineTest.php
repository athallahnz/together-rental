<?php

namespace Tests\Unit;

use App\Domain\Inventory\QuantityTimeline;
use PHPUnit\Framework\TestCase;

class QuantityTimelineTest extends TestCase
{
    public function test_adjacent_periods_reuse_stock_and_spanning_query_uses_peak(): void
    {
        $intervals = [
            ['start' => 10, 'end' => 20, 'quantity' => 3],
            ['start' => 20, 'end' => 30, 'quantity' => 2],
        ];
        $this->assertSame(3, QuantityTimeline::peak($intervals, 10, 30));
        $this->assertSame(2, QuantityTimeline::peak($intervals, 20, 30));
        $this->assertSame(0, QuantityTimeline::peak($intervals, 30, 40));
    }

    public function test_nested_and_simultaneous_intervals_accumulate_only_when_overlapping(): void
    {
        $this->assertSame(6, QuantityTimeline::peak([
            ['start' => 0, 'end' => 100, 'quantity' => 1],
            ['start' => 5, 'end' => 50, 'quantity' => 2],
            ['start' => 5, 'end' => 10, 'quantity' => 3],
            ['start' => 10, 'end' => 20, 'quantity' => 1],
        ], 0, 100));
    }

    public function test_empty_query_and_open_ended_overdue_demand(): void
    {
        $this->assertSame(0, QuantityTimeline::peak([], 10, 20));
        $this->assertSame(0, QuantityTimeline::peak([['start' => 1, 'end' => 2, 'quantity' => 4]], 2, 2));
        $this->assertSame(3, QuantityTimeline::peak([['start' => 1, 'end' => PHP_INT_MAX, 'quantity' => 3]], 100, 200));
    }
}
