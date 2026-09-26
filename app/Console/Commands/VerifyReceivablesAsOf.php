<?php

namespace App\Console\Commands;

use App\Domain\Reporting\IntegratedReportService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Non-mutating UAT acceptance gate for Stage 6B R1; expected totals come from independently
 * reviewed R0 evidence and are NOT a substitute for reconstructing unknown historical events.
 */
class VerifyReceivablesAsOf extends Command
{
    protected $signature = 'reports:verify-receivables-asof
                            {--branch= : Branch code or numeric ID}
                            {--as-of= : Past YYYY-MM-DD cutoff in app timezone}
                            {--expect-total= : Expected event-derived subtotal from R0 review}
                            {--expect-rows= : Expected positive or unverified report row count}
                            {--expect-unverified=0 : Expected number of unverifiable rows}
                            {--expect-rental=* : Rental number:as-of amount; repeat for sample records}
                            {--max=2000 : Maximum candidate rentals to inspect, capped at 2000}';

    protected $description = 'READ ONLY: verify Stage 6B R1 historical receivables against reviewed UAT evidence';

    public function handle(IntegratedReportService $reports): int
    {
        $connection = DB::connection();
        if (! app()->environment('uat') || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'together_rental_uat') {
            $this->error('SAFETY STOP: requires APP_ENV=uat, MySQL and DB=together_rental_uat.');

            return self::FAILURE;
        }

        $branchOption = trim((string) $this->option('branch'));
        $asOfOption = (string) $this->option('as-of');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $asOfOption);
        $max = filter_var($this->option('max'), FILTER_VALIDATE_INT);
        $expectedUnverified = filter_var($this->option('expect-unverified'), FILTER_VALIDATE_INT);
        if ($branchOption === '' || $date === false || $date->format('Y-m-d') !== $asOfOption
            || $asOfOption >= now()->toDateString() || $max === false || $max < 1 || $max > 2000
            || $expectedUnverified === false || $expectedUnverified < 0) {
            $this->error('Use --branch=PNG --as-of=2026-09-23 [--expect-total=1285000 --expect-rows=3]. Cutoff must precede today.');

            return self::FAILURE;
        }

        $matches = DB::table('branches')
            ->when(ctype_digit($branchOption), static fn ($query) => $query->where('id', (int) $branchOption))
            ->when(! ctype_digit($branchOption), static fn ($query) => $query->where('code', strtoupper($branchOption)))
            ->get(['id', 'company_id', 'code']);
        if ($matches->count() !== 1) {
            $this->error('Branch unknown/ambiguous. Use an exact branch ID.');

            return self::FAILURE;
        }
        /** @var stdClass $branch */
        $branch = $matches->first();
        $branchId = (int) $branch->id;
        $to = CarbonImmutable::parse($asOfOption)->endOfDay();
        $candidateCount = DB::table('rentals')->where('branch_id', $branchId)
            ->where(static function ($query) use ($to): void {
                $query->where('checked_out_at', '<=', $to)->orWhere('created_at', '<=', $to);
            })->count();
        if ($candidateCount > $max) {
            $this->error("SAFETY STOP: {$candidateCount} candidate rentals exceed --max={$max}.");

            return self::FAILURE;
        }

        $dataset = $reports->generate((int) $branch->company_id, [$branchId], [
            'from' => $to->startOfMonth(), 'to' => $to, 'branch_id' => $branchId,
            'report' => 'receivables', 'status' => 'all',
            'payment_method_id' => null, 'category_id' => null, 'search' => '',
        ]);
        $meta = $dataset['historyMeta'] ?? null;
        if ($meta === null) {
            $this->error('R1 PROJECTION MISSING: report did not return historyMeta.');

            return self::FAILURE;
        }

        $rows = collect($dataset['rows']);
        $sum = round((float) $rows->sum(static fn (array $row): float => (float) ($row['values']['balance_due'] ?? 0)), 2);
        $unverified = $rows->filter(static fn (array $row): bool => $row['values']['balance_due'] === null)->count();
        $summaryCard = collect($dataset['summary'])->firstWhere('key', 'receivables');
        $unique = $rows->pluck('id')->unique()->count() === $rows->count();
        $internallyConsistent = $unique
            && abs($sum - (float) $meta['verified_receivables']) < 0.01
            && abs($sum - (float) ($summaryCard['value'] ?? -1)) < 0.01
            && $unverified === (int) $meta['unverified_count'];

        $this->info('STAGE 6B R1 — UAT HISTORICAL RECEIVABLE RECONCILIATION (READ ONLY)');
        $this->line(sprintf('Environment: uat | DB: %s | Branch: %s (#%d) | As-of: %s | Timezone: %s',
            $connection->getDatabaseName(), $branch->code, $branchId, $to->format('Y-m-d H:i:s'), config('app.timezone')));
        $this->line(sprintf('Candidate rentals: %d | Report rows: %d | Reconstructed positive: %d | Unverifiable: %d',
            $candidateCount, $rows->count(), (int) $meta['verified_count'], $unverified));
        $this->line(sprintf('Reconstructed subtotal: %.2f | Summary card: %.2f | Internal consistency: %s',
            $sum, (float) ($summaryCard['value'] ?? 0), $internallyConsistent ? 'MATCH' : 'MISMATCH'));

        $checks = [$internallyConsistent, $unverified === $expectedUnverified];
        $expectedTotalOption = trim((string) $this->option('expect-total'));
        if ($expectedTotalOption !== '') {
            $expectedTotal = filter_var($expectedTotalOption, FILTER_VALIDATE_FLOAT);
            if ($expectedTotal === false || $expectedTotal < 0) {
                $this->error('Invalid --expect-total.');

                return self::FAILURE;
            }
            $checks[] = abs($sum - (float) $expectedTotal) < 0.01;
            $this->line(sprintf('R0 reviewed expected subtotal %.2f: %s', $expectedTotal,
                end($checks) ? 'MATCH' : 'MISMATCH'));
        }
        $expectedRowsOption = trim((string) $this->option('expect-rows'));
        if ($expectedRowsOption !== '') {
            $expectedRows = filter_var($expectedRowsOption, FILTER_VALIDATE_INT);
            if ($expectedRows === false || $expectedRows < 0) {
                $this->error('Invalid --expect-rows.');

                return self::FAILURE;
            }
            $checks[] = $rows->count() === $expectedRows;
            $this->line(sprintf('Expected rows %d: %s', $expectedRows, end($checks) ? 'MATCH' : 'MISMATCH'));
        }

        /** @var array<int, string> $expectedRentals */
        $expectedRentals = (array) $this->option('expect-rental');
        foreach ($expectedRentals as $expectedRental) {
            if (! preg_match('/^([a-zA-Z0-9-]{1,80}):([0-9]+(?:\.[0-9]{1,2})?)$/', $expectedRental, $m)) {
                $this->error('Invalid --expect-rental. Use NUMBER:AMOUNT.');

                return self::FAILURE;
            }
            $row = $rows->first(static fn (array $item): bool => $item['values']['rental_number'] === $m[1]);
            $match = $row !== null && $row['values']['balance_due'] !== null
                && abs((float) $row['values']['balance_due'] - (float) $m[2]) < 0.01;
            $checks[] = $match;
            $this->line(sprintf('Rental %s expected %.2f: %s', $m[1], (float) $m[2], $match ? 'MATCH' : 'MISMATCH'));
        }

        $this->table(['Rental', 'Historical paid', 'Historical due', 'Evidence'],
            $rows->take(15)->map(static fn (array $row): array => [
                $row['values']['rental_number'],
                $row['values']['paid_amount'] === null ? 'UNVERIFIED' : number_format((float) $row['values']['paid_amount'], 0, ',', '.'),
                $row['values']['balance_due'] === null ? 'UNVERIFIED' : number_format((float) $row['values']['balance_due'], 0, ',', '.'),
                $row['values']['history_quality'],
            ])->all());
        if (in_array(false, $checks, true)) {
            $this->error('R1 UAT RECONCILIATION FAILED; RPT-002 stays OPEN. No UAT data changed.');

            return self::FAILURE;
        }
        $this->info('R1 UAT RECONCILIATION PASS against supplied R0 evidence; no UAT data changed. Historical accounting certification remains subject to source-event completeness.');

        return self::SUCCESS;
    }
}
