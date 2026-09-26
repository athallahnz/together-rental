<?php

namespace App\Console\Commands;

use App\Domain\Reporting\IntegratedReportService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Stage 6B R0: read-only UAT diagnosis of receivable report time-travel exposure.
 * Indicative amounts are intentionally NOT an accounting-certified historical snapshot.
 */
class AuditReceivablesAsOf extends Command
{
    protected $signature = 'reports:audit-receivables-asof
                            {--list-branches : List UAT branches and stop}
                            {--branch= : Unique branch code or numeric branch ID}
                            {--as-of= : End-of-day cutoff (YYYY-MM-DD, application timezone)}
                            {--max=500 : Maximum rentals to inspect, up to 2000}
                            {--sample=12 : Maximum anonymized example rows, up to 30}';

    protected $description = 'READ ONLY: measure historical receivable report exposure in isolated Together Rental UAT';

    public function handle(IntegratedReportService $reports): int
    {
        $connection = DB::connection();
        if (! app()->environment('uat')
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'together_rental_uat') {
            $this->error('SAFETY STOP: requires APP_ENV=uat, MySQL and DB=together_rental_uat. No query executed.');

            return self::FAILURE;
        }

        if ($this->option('list-branches')) {
            $branches = DB::table('branches')->orderBy('company_id')->orderBy('code')->get(['id', 'company_id', 'code']);
            $this->table(['ID', 'Company ID', 'Branch code'], $branches->map(
                static fn (stdClass $branch): array => [(int) $branch->id, (int) $branch->company_id, (string) $branch->code],
            )->all());

            return self::SUCCESS;
        }

        $branchCode = trim((string) $this->option('branch'));
        $date = $this->parseDate((string) $this->option('as-of'));
        $max = filter_var($this->option('max'), FILTER_VALIDATE_INT);
        $sample = filter_var($this->option('sample'), FILTER_VALIDATE_INT);
        if ($branchCode === '' || $date === null || $date->greaterThan(now()->toImmutable()->endOfDay())
            || $max === false || $max < 1 || $max > 2000
            || $sample === false || $sample < 1 || $sample > 30) {
            $this->error('Usage: --branch=PNG --as-of=2026-09-15 [--max=500 --sample=12]. Date may not be in the future.');

            return self::FAILURE;
        }

        $matches = DB::table('branches')
            ->when(ctype_digit($branchCode), static fn ($query) => $query->where('id', (int) $branchCode))
            ->when(! ctype_digit($branchCode), static fn ($query) => $query->where('code', strtoupper($branchCode)))
            ->get(['id', 'company_id', 'code']);
        if ($matches->count() !== 1) {
            $this->error('Branch unknown or ambiguous. Use --list-branches and a numeric branch ID.');

            return self::FAILURE;
        }

        $branch = $matches->first();
        $branchId = (int) $branch->id;
        $cutoff = $date->endOfDay();
        $cutoffText = $cutoff->format('Y-m-d H:i:s');
        $rentals = DB::table('rentals')
            ->where('branch_id', $branchId)
            ->where(static function ($query) use ($cutoffText): void {
                $query->where('created_at', '<=', $cutoffText)
                    ->orWhere('checked_out_at', '<=', $cutoffText);
            })
            ->orderBy('id')
            ->limit($max + 1)
            ->get(['id', 'booking_id', 'rental_number', 'legacy_number', 'status', 'checked_out_at',
                'created_at', 'deleted_at', 'total_amount', 'paid_amount', 'balance_due']);
        if ($rentals->count() > $max) {
            $this->error("SAFETY STOP: rental count exceeds {$max}; narrow historical scope or increase --max up to 2000.");

            return self::FAILURE;
        }
        if ($rentals->isEmpty()) {
            $this->warn('NO HISTORICAL DATA for this cutoff and branch. Choose a later date or branch.');

            return self::FAILURE;
        }

        $rentalIds = $rentals->pluck('id')->all();
        $bookingIds = $rentals->pluck('booking_id')->filter()->unique()->values()->all();
        $paymentsQuery = DB::table('payments')->where('branch_id', $branchId)
            ->where(static function ($query) use ($rentalIds, $bookingIds): void {
                $query->whereIn('rental_id', $rentalIds);
                if ($bookingIds !== []) {
                    $query->orWhereIn('booking_id', $bookingIds);
                }
            });
        $payments = $paymentsQuery->orderBy('id')->limit(10001)->get([
            'id', 'booking_id', 'rental_id', 'direction', 'type', 'status', 'amount',
            'paid_at', 'voided_at', 'created_at',
        ]);
        if ($payments->count() > 10000) {
            $this->error('SAFETY STOP: more than 10000 related payment rows. Reduce scope.');

            return self::FAILURE;
        }
        $refunds = $payments->isEmpty() ? collect() : DB::table('refunds')
            ->where('branch_id', $branchId)
            ->whereIn('payment_id', $payments->pluck('id')->all())
            ->orderBy('id')->limit(10001)
            ->get(['id', 'payment_id', 'status', 'amount', 'processed_at', 'cancelled_at']);
        if ($refunds->count() > 10000) {
            $this->error('SAFETY STOP: more than 10000 related refund rows. Reduce scope.');

            return self::FAILURE;
        }
        $extensions = DB::table('rental_extensions')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'approved_at', 'status', 'total_amount']);
        $returns = DB::table('rental_returns')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'returned_at', 'created_at', 'total_charge_amount']);
        $adjustments = DB::table('rental_financial_adjustments')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'created_at', 'component', 'direction', 'amount']);
        $corrections = DB::table('rental_operational_corrections')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'opened_at', 'finalized_at']);

        $data = $reports->generate((int) $branch->company_id, [$branchId], [
            'from' => $date->startOfMonth()->startOfDay(),
            'to' => $cutoff,
            'branch_id' => $branchId,
            'report' => 'receivables',
            'status' => 'all',
            'payment_method_id' => null,
            'category_id' => null,
            'search' => '',
        ]);
        /** @var Collection<string, array<string, mixed>> $reported */
        $reported = collect($data['rows'])->keyBy('id');
        $paymentByRental = $payments->groupBy('rental_id');
        $paymentByBooking = $payments->filter(static fn (stdClass $p): bool => $p->booking_id !== null)->groupBy('booking_id');
        $refundByPayment = $refunds->groupBy('payment_id');
        $extensionByRental = $extensions->groupBy('rental_id');
        $returnByRental = $returns->groupBy('rental_id');
        $adjustmentByRental = $adjustments->groupBy('rental_id');
        $correctionByRental = $corrections->groupBy('rental_id');

        $riskCounts = [];
        $examples = [];
        $mirroredRows = 0;
        $mirroredBalance = 0.0;
        $asOfCheckedOut = 0;
        $exposed = 0;
        $indicativeCount = 0;
        $indicativeTotal = 0.0;
        $indicativeReportedTotal = 0.0;
        $indicativeDifferent = 0;
        $possibleMissing = 0;
        $sampleCandidates = [];

        foreach ($rentals as $rental) {
            $id = (int) $rental->id;
            $bookingId = $rental->booking_id === null ? null : (int) $rental->booking_id;
            $currentValid = ! in_array((string) $rental->status, ['draft', 'cancelled', 'void', 'rejected'], true);
            $createdBefore = (string) $rental->created_at <= $cutoffText;
            $currentlyIncluded = $createdBefore && $currentValid && $rental->deleted_at === null
                && (float) $rental->balance_due > 0.009;
            $reportedRow = $reported->get('receivable-'.$id);
            if ($currentlyIncluded) {
                $mirroredRows++;
                $mirroredBalance += (float) $rental->balance_due;
            }

            $checkedOut = $rental->checked_out_at !== null && (string) $rental->checked_out_at <= $cutoffText;
            $asOfCheckedOut += (int) $checkedOut;
            $flags = [];
            if ($rental->checked_out_at === null) {
                $flags[] = 'NO_CHECKOUT_TIMESTAMP';
            } elseif (! $checkedOut && $currentlyIncluded) {
                $flags[] = 'FUTURE_CHECKOUT_INCLUDED';
            }
            if ($rental->deleted_at !== null && (string) $rental->deleted_at > $cutoffText && $checkedOut) {
                $flags[] = 'DELETED_AFTER_CUTOFF';
            }
            if ($checkedOut && ! $currentValid) {
                $flags[] = 'CURRENT_STATUS_CANNOT_PROVE_PAST';
            }
            if ($checkedOut && ! $createdBefore) {
                $flags[] = 'IMPORTED_AFTER_CUTOFF';
            }
            if ($rental->legacy_number !== null) {
                $flags[] = 'LEGACY_WITHOUT_COMPLETE_EVENT_HISTORY';
            }

            $relatedPayments = $paymentByRental->get($id, collect())
                ->concat($bookingId === null ? collect() : $paymentByBooking->get($bookingId, collect()))
                ->unique('id');
            $historicalPaid = 0.0;
            $laterPayments = 0.0;
            $laterRefunds = 0.0;
            foreach ($relatedPayments as $payment) {
                if ($payment->direction !== 'in' || $payment->type !== 'rental') {
                    continue; // Security deposit is never rental receivable settlement.
                }
                if ($bookingId !== null && $payment->booking_id !== null
                    && (int) $payment->booking_id === $bookingId
                    && $payment->rental_id !== null && (int) $payment->rental_id !== $id) {
                    $flags[] = 'CONFLICTING_PAYMENT_LINK';
                    continue;
                }
                $paidAt = (string) $payment->paid_at;
                $voidedAfter = $payment->status === 'void' && $payment->voided_at !== null
                    && (string) $payment->voided_at > $cutoffText;
                $historicallyValid = $payment->status === 'completed' || $voidedAfter;
                if ($payment->status === 'void' && $payment->voided_at === null) {
                    $flags[] = 'VOID_WITHOUT_TIMESTAMP';
                }
                if ($paidAt <= $cutoffText && (string) $payment->created_at > $cutoffText) {
                    $flags[] = 'BACKDATED_PAYMENT_CREATED_LATER';
                }
                if ($paidAt > $cutoffText && $payment->status === 'completed') {
                    $laterPayments += (float) $payment->amount;
                }
                if ($voidedAfter && $paidAt <= $cutoffText) {
                    $flags[] = 'VOID_AFTER_CUTOFF';
                }
                $paidRefund = 0.0;
                foreach ($refundByPayment->get((int) $payment->id, collect()) as $refund) {
                    if ($refund->processed_at === null) {
                        continue;
                    }
                    if ((string) $refund->processed_at > $cutoffText && $refund->status === 'paid') {
                        $laterRefunds += (float) $refund->amount;
                    }
                    if ((string) $refund->processed_at <= $cutoffText && $refund->status !== 'paid') {
                        $flags[] = 'REFUND_PAST_STATE_UNCERTAIN';
                    }
                    if ($refund->status === 'paid' && (string) $refund->processed_at <= $cutoffText) {
                        $paidRefund += (float) $refund->amount;
                    }
                }
                if ($paidAt <= $cutoffText && $historicallyValid) {
                    $historicalPaid += max(0, (float) $payment->amount - $paidRefund);
                }
            }
            if ($laterPayments > 0.009) {
                $flags[] = 'PAYMENTS_AFTER_CUTOFF';
            }
            if ($laterRefunds > 0.009) {
                $flags[] = 'PAID_REFUNDS_AFTER_CUTOFF';
            }
            $priceAfter = false;
            foreach ($extensionByRental->get($id, collect()) as $extension) {
                if ($extension->approved_at !== null && (string) $extension->approved_at > $cutoffText
                    && ! in_array((string) $extension->status, ['cancelled', 'rejected', 'void'], true)) {
                    $flags[] = 'EXTENSION_APPROVED_LATER';
                    $priceAfter = true;
                }
            }
            foreach ($returnByRental->get($id, collect()) as $return) {
                if ((string) $return->returned_at > $cutoffText) {
                    $flags[] = 'RETURN_AFTER_CUTOFF';
                    if (abs((float) $return->total_charge_amount) > 0.009) {
                        $priceAfter = true;
                        $flags[] = 'RETURN_CHARGE_AFTER_CUTOFF';
                    }
                }
                if ((string) $return->created_at > $cutoffText && (string) $return->returned_at <= $cutoffText) {
                    $flags[] = 'BACKDATED_RETURN_CREATED_LATER';
                }
            }
            foreach ($adjustmentByRental->get($id, collect()) as $adjustment) {
                if ((string) $adjustment->created_at > $cutoffText) {
                    $flags[] = 'FINANCIAL_ADJUSTMENT_AFTER_CUTOFF';
                    if ($adjustment->component === 'charge') {
                        $priceAfter = true;
                    }
                } elseif ($adjustment->component === 'payment') {
                    $historicalPaid += $adjustment->direction === 'increase'
                        ? (float) $adjustment->amount : -(float) $adjustment->amount;
                }
            }
            foreach ($correctionByRental->get($id, collect()) as $correction) {
                if ((string) $correction->opened_at > $cutoffText
                    || ($correction->finalized_at !== null && (string) $correction->finalized_at > $cutoffText)) {
                    $flags[] = 'OPERATIONAL_CORRECTION_AFTER_CUTOFF';
                    $priceAfter = true;
                }
            }
            if ($priceAfter) {
                $flags[] = 'PAST_TOTAL_NEEDS_EVENT_RECONSTRUCTION';
            }

            $flags = array_values(array_unique($flags));
            foreach ($flags as $flag) {
                $riskCounts[$flag] = ($riskCounts[$flag] ?? 0) + 1;
            }
            $exposed += (int) ($flags !== []);
            $indicative = null;
            $blockingFlags = ['NO_CHECKOUT_TIMESTAMP', 'DELETED_AFTER_CUTOFF', 'CURRENT_STATUS_CANNOT_PROVE_PAST',
                'IMPORTED_AFTER_CUTOFF', 'LEGACY_WITHOUT_COMPLETE_EVENT_HISTORY', 'CONFLICTING_PAYMENT_LINK',
                'VOID_WITHOUT_TIMESTAMP', 'BACKDATED_PAYMENT_CREATED_LATER', 'REFUND_PAST_STATE_UNCERTAIN',
                'PAST_TOTAL_NEEDS_EVENT_RECONSTRUCTION', 'BACKDATED_RETURN_CREATED_LATER'];
            if ($checkedOut && array_intersect($flags, $blockingFlags) === []) {
                $indicative = max(0, round((float) $rental->total_amount - $historicalPaid, 2));
                $indicativeCount++;
                $indicativeTotal += $indicative;
                $indicativeReportedTotal += $reportedRow === null ? 0.0 : (float) $reportedRow['values']['balance_due'];
                if (abs($indicative - ($reportedRow === null ? 0.0 : (float) $reportedRow['values']['balance_due'])) > 0.009) {
                    $indicativeDifferent++;
                    if ($reportedRow === null && $indicative > 0.009) {
                        $possibleMissing++;
                    }
                }
            }
            if (count($examples) < $sample && ($flags !== [] || $indicative !== null && abs($indicative - ($reportedRow === null ? 0.0 : (float) $reportedRow['values']['balance_due'])) > 0.009)) {
                $examples[] = [
                    (string) $rental->rental_number,
                    $reportedRow === null ? 'not listed' : number_format((float) $reportedRow['values']['balance_due'], 0, ',', '.'),
                    $indicative === null ? 'unverified' : number_format($indicative, 0, ',', '.'),
                    $flags === [] ? 'INDICATIVE' : implode(', ', $flags),
                ];
            }
        }

        $reportBalance = round((float) collect($data['rows'])->sum(static fn (array $row): float => (float) $row['values']['balance_due']), 2);
        $reportMatchesMirror = $reported->count() === $mirroredRows && abs($reportBalance - $mirroredBalance) < 0.01;
        arsort($riskCounts);
        $this->info('STAGE 6B R0 — UAT HISTORICAL RECEIVABLE EXPOSURE (READ ONLY)');
        $this->line(sprintf('Environment: uat | DB: %s | Branch: %s (#%d) | As-of: %s | Timezone: %s',
            $connection->getDatabaseName(), $branch->code, $branchId, $cutoffText, config('app.timezone')));
        $this->line(sprintf('Historical candidate rentals: %d | Checked out by cutoff: %d | Exposure flags on %d rentals',
            $rentals->count(), $asOfCheckedOut, $exposed));
        $this->line(sprintf('EXISTING REPORT: %d rows; receivable %.2f | Baseline mirror: %d rows; %.2f | %s',
            $reported->count(), $reportBalance, $mirroredRows, $mirroredBalance, $reportMatchesMirror ? 'MATCH' : 'MISMATCH'));
        $this->line(sprintf('INDICATIVE CLEAN SUBSET ONLY: %d rentals; as-of due %.2f vs existing displayed %.2f; different %d; possibly omitted %d',
            $indicativeCount, $indicativeTotal, $indicativeReportedTotal, $indicativeDifferent, $possibleMissing));
        $this->warn('Indicative amounts are NOT certified historical balances: exclude flagged uncertain records; no snapshots are rewritten.');
        if ($riskCounts !== []) {
            $this->table(['Exposure reason', 'Rentals'], array_map(
                static fn (string $key, int $count): array => [$key, $count],
                array_keys($riskCounts), array_values($riskCounts),
            ));
        }
        if ($examples !== []) {
            $this->table(['Rental', 'Existing reported due', 'Indicative as-of due', 'Exposure notes'], $examples);
        }
        if (! $reportMatchesMirror) {
            $this->error('AUDIT FAIL: original report differs from exact current-state query mirror. Review data and joins.');

            return self::FAILURE;
        }
        $this->info('UAT DIAGNOSTIC COMPLETE: read-only exposure measured. RPT-002 remains OPEN until historical logic is corrected and verified.');

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
