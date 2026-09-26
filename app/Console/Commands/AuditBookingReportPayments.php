<?php

namespace App\Console\Commands;

use App\Domain\Finance\BookingPaymentSettlement;
use App\Domain\Reporting\IntegratedReportService;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reconciliation of the ACTUAL UAT booking records against the
 * operational report and the authoritative booking payment settlement.
 * Never creates test transactions and never modifies existing UAT records.
 */
class AuditBookingReportPayments extends Command
{
    protected $signature = 'reports:audit-booking-payments
                            {--list-branches : Show UAT branch codes and stop}
                            {--branch= : Required branch code or numeric ID}
                            {--from= : Inclusive booking date YYYY-MM-DD}
                            {--to= : Inclusive booking date YYYY-MM-DD}
                            {--max=500 : Maximum bookings to inspect (abort if exceeded)}
                            {--sample=12 : Maximum example rows in output}';

    protected $description = 'READ ONLY: reconcile operational booking report DP against actual UAT payments and paid refunds';

    public function handle(
        IntegratedReportService $reports,
        BookingPaymentSettlement $settlement,
    ): int {
        $connection = DB::connection();
        if (! app()->environment('uat')
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'together_rental_uat') {
            $this->error('SAFETY STOP: requires APP_ENV=uat, MySQL, and database together_rental_uat.');

            return self::FAILURE;
        }

        if ($this->option('list-branches')) {
            $branches = DB::table('branches')->orderBy('company_id')->orderBy('code')->get(['id', 'company_id', 'code']);
            $this->table(['ID', 'Company ID', 'Branch code'], $branches->map(
                static fn ($branch): array => [(int) $branch->id, (int) $branch->company_id, (string) $branch->code],
            )->all());

            return self::SUCCESS;
        }

        $branchCode = trim((string) $this->option('branch'));
        $fromDate = $this->parseDate((string) $this->option('from'));
        $toDate = $this->parseDate((string) $this->option('to'));
        $max = filter_var($this->option('max'), FILTER_VALIDATE_INT);
        $sample = filter_var($this->option('sample'), FILTER_VALIDATE_INT);

        if ($branchCode === '' || $fromDate === null || $toDate === null
            || $fromDate->greaterThan($toDate)
            || $fromDate->diffInDays($toDate) > 366
            || $max === false || $max < 1 || $max > 2000
            || $sample === false || $sample < 1 || $sample > 30) {
            $this->error('Usage: --branch=PNG --from=2026-09-01 --to=2026-09-26 [--max=500 --sample=12]. Period <=367 days.');

            return self::FAILURE;
        }

        $matchingBranches = DB::table('branches')
            ->when(ctype_digit($branchCode), static fn ($query) => $query->where('id', (int) $branchCode))
            ->when(! ctype_digit($branchCode), static fn ($query) => $query->where('code', strtoupper($branchCode)))
            ->get(['id', 'company_id', 'code']);
        if ($matchingBranches->count() !== 1) {
            $this->error('Branch not found or ambiguous; run --list-branches and select a numeric branch ID.');

            return self::FAILURE;
        }

        $branch = $matchingBranches->first();
        $bookingRows = Booking::query()
            ->where('branch_id', (int) $branch->id)
            ->whereBetween('booked_at', [$fromDate->startOfDay(), $toDate->endOfDay()])
            ->orderBy('id')
            ->limit($max + 1)
            ->get(['id', 'booking_number', 'branch_id', 'total_amount', 'deposit_paid', 'booked_at']);
        if ($bookingRows->count() > $max) {
            $this->error("SAFETY STOP: more than {$max} bookings in this period. Narrow --from/--to or increase --max up to 2000.");

            return self::FAILURE;
        }
        if ($bookingRows->isEmpty()) {
            $this->warn('NO UAT DATA in selected period; no live financial comparison was possible. Choose another date range.');

            return self::FAILURE;
        }

        $filters = [
            'from' => $fromDate->startOfDay(),
            'to' => $toDate->endOfDay(),
            'branch_id' => (int) $branch->id,
            'report' => 'operational',
            'status' => 'all',
            'payment_method_id' => null,
            'category_id' => null,
            'search' => '',
        ];
        $actualReport = $reports->generate((int) $branch->company_id, [(int) $branch->id], $filters);
        $reportById = collect($actualReport['rows'])
            ->filter(static fn (array $row): bool => $row['values']['kind'] === 'Booking')
            ->keyBy('id');

        $changed = 0;
        $mismatches = 0;
        $depositOnly = 0;
        $refunded = 0;
        $oldTotal = 0.0;
        $newTotal = 0.0;
        $oldBalanceTotal = 0.0;
        $newBalanceTotal = 0.0;
        $examples = [];

        foreach ($bookingRows as $booking) {
            $canonical = $settlement->summary($booking);
            $expectedPaid = (float) $canonical['rental_paid'];
            $expectedDue = max(0, round((float) $booking->total_amount - $expectedPaid, 2));
            $previousPaid = (float) $booking->deposit_paid;
            $previousDue = max(0, round((float) $booking->total_amount - $previousPaid, 2));
            $row = $reportById->get('booking-'.$booking->id);
            $matches = $row !== null
                && abs((float) $row['values']['paid_amount'] - $expectedPaid) < 0.01
                && abs((float) $row['values']['balance_due'] - $expectedDue) < 0.01;
            $affected = abs($previousPaid - $expectedPaid) >= 0.01
                || abs($previousDue - $expectedDue) >= 0.01;

            $changed += (int) $affected;
            $mismatches += (int) ! $matches;
            $depositOnly += (int) ($canonical['deposit_paid'] > 0 && $canonical['rental_paid'] === 0.0);
            $refunded += (int) ($canonical['rental_refunded'] > 0);
            $oldTotal += $previousPaid;
            $newTotal += $expectedPaid;
            $oldBalanceTotal += $previousDue;
            $newBalanceTotal += $expectedDue;

            if (count($examples) < $sample && ($affected || ! $matches)) {
                $examples[] = [
                    (string) $booking->booking_number,
                    number_format($previousPaid, 0, ',', '.'),
                    number_format($expectedPaid, 0, ',', '.'),
                    number_format($previousDue, 0, ',', '.'),
                    number_format($expectedDue, 0, ',', '.'),
                    $matches ? 'MATCH' : 'MISMATCH',
                ];
            }
        }

        $this->info('UAT STAGE 6A BOOKING REPORT RECONCILIATION (READ ONLY)');
        $this->line(sprintf('Environment: %s | DB: %s | Branch: %s (#%d) | Period: %s to %s',
            app()->environment(), $connection->getDatabaseName(), $branch->code, $branch->id,
            $fromDate->toDateString(), $toDate->toDateString()));
        $this->line(sprintf('Measured actual bookings: %d | Previously misreported: %d | Deposit-only: %d | Rental refunds paid: %d',
            $bookingRows->count(), $changed, $depositOnly, $refunded));
        $this->line(sprintf('Previous report rental paid: %.2f | Corrected rental paid: %.2f | Difference: %+.2f',
            $oldTotal, $newTotal, $newTotal - $oldTotal));
        $this->line(sprintf('Previous report booking balance: %.2f | Corrected booking balance: %.2f | Difference: %+.2f',
            $oldBalanceTotal, $newBalanceTotal, $newBalanceTotal - $oldBalanceTotal));
        $this->line(sprintf('Corrected report vs canonical BookingPaymentSettlement mismatches: %d', $mismatches));
        if ($examples !== []) {
            $this->table(['Booking', 'Before paid', 'After paid', 'Before due', 'After due', 'Check'], $examples);
        }
        if ($mismatches !== 0) {
            $this->error('UAT FAIL: corrected report does not agree with canonical source payment settlement.');

            return self::FAILURE;
        }

        $this->info('UAT PASS: measured live records agree with corrected booking payment report; no UAT data changed.');

        return self::SUCCESS;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
