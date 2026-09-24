<?php

namespace App\Domain\Inventory;

final class QuantityTimeline
{
    /**
     * Half-open intervals: a return at 10:00 can serve a booking starting at 10:00.
     *
     * @param  list<array{start: int, end: int, quantity: int}>  $intervals
     */
    public static function peak(array $intervals, int $start = PHP_INT_MIN, int $end = PHP_INT_MAX): int
    {
        $events = [];
        foreach ($intervals as $interval) {
            $from = max($start, $interval['start']);
            $until = min($end, $interval['end']);
            if ($from >= $until || $interval['quantity'] <= 0) {
                continue;
            }
            $events[$from] = ($events[$from] ?? 0) + $interval['quantity'];
            $events[$until] = ($events[$until] ?? 0) - $interval['quantity'];
        }
        ksort($events, SORT_NUMERIC);
        $current = 0;
        $peak = 0;
        foreach ($events as $delta) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
