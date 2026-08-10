<?php

namespace App\Domain\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class FinanceDashboardService
{
    /**
     * @param  list<int>  $branchIds
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, branch_id: int|null}  $filters
     * @return array<string, mixed>
     */
    public function summarize(int $companyId, array $branchIds, array $filters): array
    {
        $scopedBranchIds = $filters['branch_id'] === null
            ? $branchIds
            : array_values(array_intersect($branchIds, [$filters['branch_id']]));
        $periodDays = (int) $filters['from']->startOfDay()
            ->diffInDays($filters['to']->startOfDay()) + 1;
        $previousTo = $filters['from']->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($periodDays - 1)->startOfDay();

        $current = $this->periodMetrics(
            $scopedBranchIds,
            $filters['from'],
            $filters['to'],
        );
        $previous = $this->periodMetrics(
            $scopedBranchIds,
            $previousFrom,
            $previousTo,
        );
        $currentState = $this->currentState($scopedBranchIds);

        return [
            'summary' => [
                ...$current,
                ...$currentState,
            ],
            'comparison' => [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
                'gross_collections_percent' => $this->percentageChange(
                    $current['gross_collections'],
                    $previous['gross_collections'],
                ),
                'rental_collections_percent' => $this->percentageChange(
                    $current['rental_collections'],
                    $previous['rental_collections'],
                ),
                'paid_refunds_percent' => $this->percentageChange(
                    $current['paid_refunds'],
                    $previous['paid_refunds'],
                ),
                'net_cash_flow_percent' => $this->percentageChange(
                    $current['net_cash_flow'],
                    $previous['net_cash_flow'],
                ),
            ],
            'trend' => $this->trend(
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'paymentMethods' => $this->paymentMethods(
                $companyId,
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
                $current['gross_collections'],
            ),
            'sourceContexts' => $this->sourceContexts(
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'branchPerformance' => $this->branchPerformance(
                $companyId,
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'recentActivity' => $this->recentActivity(
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'attention' => $this->attention(
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'methodology' => [
                'gross_collections' => 'Payment completed berarah masuk pada periode. Deposit termasuk di sini, tetapi ditampilkan terpisah dari penerimaan rental.',
                'net_cash_flow' => 'Payment masuk dikurangi payment keluar dan refund berstatus paid. Payment void tidak dihitung.',
                'deposit_held' => 'Deposit completed sepanjang waktu dikurangi refund paid yang bersumber dari payment deposit.',
                'receivables' => 'Saldo rental positif saat ini, tidak termasuk rental draft, cancelled, void, atau rejected.',
                'comparison' => 'Persentase dibandingkan dengan periode sebelumnya yang memiliki jumlah hari sama.',
            ],
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     gross_collections: float,
     *     rental_collections: float,
     *     deposit_collections: float,
     *     operating_outflows: float,
     *     paid_refunds: float,
     *     net_cash_flow: float,
     *     completed_payment_count: int,
     *     inbound_payment_count: int,
     *     average_collection: float,
     *     void_count: int,
     *     void_amount: float,
     *     paid_refund_count: int
     * }
     */
    private function periodMetrics(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        if ($branchIds === []) {
            return [
                'gross_collections' => 0.0,
                'rental_collections' => 0.0,
                'deposit_collections' => 0.0,
                'operating_outflows' => 0.0,
                'paid_refunds' => 0.0,
                'net_cash_flow' => 0.0,
                'completed_payment_count' => 0,
                'inbound_payment_count' => 0,
                'average_collection' => 0.0,
                'void_count' => 0,
                'void_amount' => 0.0,
                'paid_refund_count' => 0,
            ];
        }

        $payment = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as gross_collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' AND type = 'rental' THEN amount ELSE 0 END), 0) as rental_collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' AND type = 'deposit' THEN amount ELSE 0 END), 0) as deposit_collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as operating_outflows")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as payment_net")
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) as inbound_count")
            ->selectRaw('COUNT(*) as completed_count')
            ->first();
        $void = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'void')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as void_count, COALESCE(SUM(amount), 0) as void_amount')
            ->first();
        $refund = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('COUNT(*) as refund_count, COALESCE(SUM(amount), 0) as refund_amount')
            ->first();

        $grossCollections = (float) ($payment->gross_collections ?? 0);
        $inboundCount = (int) ($payment->inbound_count ?? 0);
        $paidRefunds = (float) ($refund->refund_amount ?? 0);

        return [
            'gross_collections' => $grossCollections,
            'rental_collections' => (float) ($payment->rental_collections ?? 0),
            'deposit_collections' => (float) ($payment->deposit_collections ?? 0),
            'operating_outflows' => (float) ($payment->operating_outflows ?? 0),
            'paid_refunds' => $paidRefunds,
            'net_cash_flow' => (float) ($payment->payment_net ?? 0) - $paidRefunds,
            'completed_payment_count' => (int) ($payment->completed_count ?? 0),
            'inbound_payment_count' => $inboundCount,
            'average_collection' => $inboundCount === 0
                ? 0.0
                : round($grossCollections / $inboundCount, 2),
            'void_count' => (int) ($void->void_count ?? 0),
            'void_amount' => (float) ($void->void_amount ?? 0),
            'paid_refund_count' => (int) ($refund->refund_count ?? 0),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     outstanding_refund_count: int,
     *     outstanding_refund_amount: float,
     *     receivable_count: int,
     *     receivable_amount: float,
     *     deposit_held: float,
     *     open_cash_session_count: int
     * }
     */
    private function currentState(array $branchIds): array
    {
        if ($branchIds === []) {
            return [
                'outstanding_refund_count' => 0,
                'outstanding_refund_amount' => 0.0,
                'receivable_count' => 0,
                'receivable_amount' => 0.0,
                'deposit_held' => 0.0,
                'open_cash_session_count' => 0,
            ];
        }

        $refund = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['requested', 'approved'])
            ->selectRaw('COUNT(*) as refund_count, COALESCE(SUM(amount), 0) as refund_amount')
            ->first();
        $receivable = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where('balance_due', '>', 0)
            ->selectRaw('COUNT(*) as rental_count, COALESCE(SUM(balance_due), 0) as rental_amount')
            ->first();
        $depositPayments = (float) DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->sum('amount');
        $depositRefunds = (float) DB::table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->whereIn('refunds.branch_id', $branchIds)
            ->where('refunds.status', 'paid')
            ->where('payments.type', 'deposit')
            ->sum('refunds.amount');
        $openSessions = DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->whereIn('cash_registers.branch_id', $branchIds)
            ->where('cash_sessions.status', 'open')
            ->count();

        return [
            'outstanding_refund_count' => (int) ($refund->refund_count ?? 0),
            'outstanding_refund_amount' => (float) ($refund->refund_amount ?? 0),
            'receivable_count' => (int) ($receivable->rental_count ?? 0),
            'receivable_amount' => (float) ($receivable->rental_amount ?? 0),
            'deposit_held' => max(0, $depositPayments - $depositRefunds),
            'open_cash_session_count' => $openSessions,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{key: string, label: string, collections: float, expenses: float, refunds: float, net: float}>
     */
    private function trend(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $monthly = $from->startOfDay()->diffInDays($to->startOfDay()) > 62;
        $buckets = [];
        $cursor = $monthly ? $from->startOfMonth() : $from->startOfDay();
        $last = $monthly ? $to->startOfMonth() : $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] = [
                'key' => $key,
                'label' => $monthly ? $cursor->format('M Y') : $cursor->format('d M'),
                'collections' => 0.0,
                'expenses' => 0.0,
                'refunds' => 0.0,
                'net' => 0.0,
            ];
            $cursor = $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        if ($branchIds === []) {
            return array_values($buckets);
        }

        /** @var Collection<int, stdClass> $payments */
        $payments = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as occurred_on, direction, SUM(amount) as total')
            ->groupBy('occurred_on', 'direction')
            ->get();

        foreach ($payments as $row) {
            $date = CarbonImmutable::parse((string) $row->occurred_on);
            $key = $monthly ? $date->format('Y-m') : $date->toDateString();
            if (! isset($buckets[$key])) {
                continue;
            }
            $amount = (float) $row->total;
            if ((string) $row->direction === 'in') {
                $buckets[$key]['collections'] += $amount;
                $buckets[$key]['net'] += $amount;
            } else {
                $buckets[$key]['expenses'] += $amount;
                $buckets[$key]['net'] -= $amount;
            }
        }

        /** @var Collection<int, stdClass> $refunds */
        $refunds = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('DATE(processed_at) as occurred_on, SUM(amount) as total')
            ->groupBy('occurred_on')
            ->get();

        foreach ($refunds as $row) {
            $date = CarbonImmutable::parse((string) $row->occurred_on);
            $key = $monthly ? $date->format('Y-m') : $date->toDateString();
            if (! isset($buckets[$key])) {
                continue;
            }
            $amount = (float) $row->total;
            $buckets[$key]['refunds'] += $amount;
            $buckets[$key]['net'] -= $amount;
        }

        return array_values($buckets);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, code: string, name: string, type: string, transaction_count: int, collections: float, refunds: float, net: float, share_percent: float}>
     */
    private function paymentMethods(
        int $companyId,
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
        float $grossCollections,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('payment_methods as methods')
            ->leftJoin('payments', function ($join) use ($branchIds, $from, $to): void {
                $join->on('payments.payment_method_id', '=', 'methods.id')
                    ->whereIn('payments.branch_id', $branchIds)
                    ->where('payments.status', 'completed')
                    ->where('payments.direction', 'in')
                    ->whereBetween('payments.paid_at', [$from, $to]);
            })
            ->where('methods.company_id', $companyId)
            ->select([
                'methods.id',
                'methods.code',
                'methods.name',
                'methods.type',
            ])
            ->selectRaw('COUNT(payments.id) as transaction_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) as collections')
            ->groupBy('methods.id', 'methods.code', 'methods.name', 'methods.type', 'methods.sort_order')
            ->orderBy('methods.sort_order')
            ->orderBy('methods.name')
            ->get();
        $refunds = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('payment_method_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method_id')
            ->pluck('total', 'payment_method_id');

        return array_values($rows
            ->map(static function (stdClass $row) use ($refunds, $grossCollections): array {
                $collections = (float) $row->collections;
                $refundAmount = (float) ($refunds->get((int) $row->id, 0));

                return [
                    'id' => (int) $row->id,
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'type' => (string) $row->type,
                    'transaction_count' => (int) $row->transaction_count,
                    'collections' => $collections,
                    'refunds' => $refundAmount,
                    'net' => $collections - $refundAmount,
                    'share_percent' => $grossCollections <= 0
                        ? 0.0
                        : round(($collections / $grossCollections) * 100, 1),
                ];
            })
            ->filter(static fn (array $row): bool => $row['collections'] > 0 || $row['refunds'] > 0)
            ->sortByDesc('collections')
            ->values()
            ->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{context: string, label: string, transaction_count: int, inflow: float, outflow: float, net: float}>
     */
    private function sourceContexts(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("COALESCE(source_context, 'unknown') as source_context")
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as inflow")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outflow")
            ->groupBy('source_context')
            ->get();
        $labels = [
            'booking' => 'Booking',
            'rental_checkout' => 'Checkout Rental',
            'rental_return' => 'Pengembalian Rental',
            'rental_extension' => 'Perpanjangan Rental',
            'transfer_expense' => 'Biaya Transfer Aset',
            'unknown' => 'Tanpa konteks',
        ];

        return array_values($rows
            ->map(static function (stdClass $row) use ($labels): array {
                $context = (string) $row->source_context;
                $inflow = (float) $row->inflow;
                $outflow = (float) $row->outflow;

                return [
                    'context' => $context,
                    'label' => $labels[$context] ?? $context,
                    'transaction_count' => (int) $row->transaction_count,
                    'inflow' => $inflow,
                    'outflow' => $outflow,
                    'net' => $inflow - $outflow,
                ];
            })
            ->sortByDesc('inflow')
            ->values()
            ->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, code: string, name: string, transaction_count: int, collections: float, outflows: float, refunds: float, net: float, receivables: float}>
     */
    private function branchPerformance(
        int $companyId,
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $branches */
        $branches = DB::table('branches')
            ->where('company_id', $companyId)
            ->whereIn('id', $branchIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $payments = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('branch_id, COUNT(*) as transaction_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outflows")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $refunds = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('branch_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');
        $receivables = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where('balance_due', '>', 0)
            ->selectRaw('branch_id, COALESCE(SUM(balance_due), 0) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        return array_values($branches->map(static function (stdClass $branch) use (
            $payments,
            $refunds,
            $receivables,
        ): array {
            $payment = $payments->get($branch->id);
            $collections = (float) data_get($payment, 'collections', 0);
            $outflows = (float) data_get($payment, 'outflows', 0);
            $refundAmount = (float) ($refunds->get($branch->id, 0));

            return [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'transaction_count' => (int) data_get($payment, 'transaction_count', 0),
                'collections' => $collections,
                'outflows' => $outflows,
                'refunds' => $refundAmount,
                'net' => $collections - $outflows - $refundAmount,
                'receivables' => (float) ($receivables->get($branch->id, 0)),
            ];
        })->sortByDesc('net')->values()->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{kind: string, id: int, number: string, branch_code: string, customer_name: string|null, status: string, amount: float, direction: string, occurred_at: string, href: string}>
     */
    private function recentActivity(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $payments */
        $payments = DB::table('payments')
            ->join('branches', 'branches.id', '=', 'payments.branch_id')
            ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
            ->whereIn('payments.branch_id', $branchIds)
            ->whereBetween('payments.paid_at', [$from, $to])
            ->orderByDesc('payments.paid_at')
            ->limit(12)
            ->get([
                'payments.id',
                'payments.payment_number as number',
                'payments.status',
                'payments.direction',
                'payments.amount',
                'payments.paid_at as occurred_at',
                'branches.code as branch_code',
                'customers.name as customer_name',
            ]);
        /** @var Collection<int, stdClass> $refunds */
        $refunds = DB::table('refunds')
            ->join('branches', 'branches.id', '=', 'refunds.branch_id')
            ->leftJoin('payments', 'payments.id', '=', 'refunds.payment_id')
            ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
            ->whereIn('refunds.branch_id', $branchIds)
            ->whereBetween('refunds.created_at', [$from, $to])
            ->orderByDesc('refunds.created_at')
            ->limit(12)
            ->get([
                'refunds.id',
                'refunds.refund_number as number',
                'refunds.status',
                'refunds.amount',
                'refunds.created_at as occurred_at',
                'branches.code as branch_code',
                'customers.name as customer_name',
            ]);

        return array_values($payments
            ->map(static fn (stdClass $row): array => [
                'kind' => 'payment',
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'branch_code' => (string) $row->branch_code,
                'customer_name' => $row->customer_name === null
                    ? null
                    : (string) $row->customer_name,
                'status' => (string) $row->status,
                'amount' => (float) $row->amount,
                'direction' => (string) $row->direction,
                'occurred_at' => (string) $row->occurred_at,
                'href' => '/finance/payments/'.(int) $row->id,
            ])
            ->merge($refunds->map(static fn (stdClass $row): array => [
                'kind' => 'refund',
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'branch_code' => (string) $row->branch_code,
                'customer_name' => $row->customer_name === null
                    ? null
                    : (string) $row->customer_name,
                'status' => (string) $row->status,
                'amount' => (float) $row->amount,
                'direction' => 'out',
                'occurred_at' => (string) $row->occurred_at,
                'href' => '/finance/refunds/'.(int) $row->id,
            ]))
            ->sortByDesc('occurred_at')
            ->take(12)
            ->values()
            ->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     requested_refunds: array{count: int, amount: float},
     *     approved_refunds: array{count: int, amount: float},
     *     overdue_receivables: array{count: int, amount: float},
     *     cash_differences: array{count: int, amount: float},
     *     ledger_integrity: array{cash_payment_without_ledger: int, cash_refund_without_ledger: int}
     * }
     */
    private function attention(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $empty = [
            'requested_refunds' => ['count' => 0, 'amount' => 0.0],
            'approved_refunds' => ['count' => 0, 'amount' => 0.0],
            'overdue_receivables' => ['count' => 0, 'amount' => 0.0],
            'cash_differences' => ['count' => 0, 'amount' => 0.0],
            'ledger_integrity' => [
                'cash_payment_without_ledger' => 0,
                'cash_refund_without_ledger' => 0,
            ],
        ];

        if ($branchIds === []) {
            return $empty;
        }

        $refundStatus = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['requested', 'approved'])
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $requested = $refundStatus->get('requested');
        $approved = $refundStatus->get('approved');
        $overdue = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['returned', 'completed', 'closed', 'cancelled', 'void', 'rejected'])
            ->where('due_at', '<', now())
            ->where('balance_due', '>', 0)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(balance_due), 0) as total_amount')
            ->first();
        $cashDifference = DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->whereIn('cash_registers.branch_id', $branchIds)
            ->where('cash_sessions.status', 'closed')
            ->whereBetween('cash_sessions.closed_at', [$from, $to])
            ->where('cash_sessions.difference_amount', '!=', 0)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(ABS(cash_sessions.difference_amount)), 0) as total_amount')
            ->first();
        $cashPaymentsWithoutLedger = DB::table('payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->whereIn('payments.branch_id', $branchIds)
            ->where('payments.status', 'completed')
            ->where('payment_methods.type', 'cash')
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('cash_transactions')
                    ->whereColumn('cash_transactions.payment_id', 'payments.id');
            })
            ->count();
        $cashRefundsWithoutLedger = DB::table('refunds')
            ->join('payment_methods', 'payment_methods.id', '=', 'refunds.payment_method_id')
            ->whereIn('refunds.branch_id', $branchIds)
            ->where('refunds.status', 'paid')
            ->where('payment_methods.type', 'cash')
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('cash_transactions')
                    ->whereColumn('cash_transactions.refund_id', 'refunds.id');
            })
            ->count();

        return [
            'requested_refunds' => [
                'count' => (int) data_get($requested, 'total_count', 0),
                'amount' => (float) data_get($requested, 'total_amount', 0),
            ],
            'approved_refunds' => [
                'count' => (int) data_get($approved, 'total_count', 0),
                'amount' => (float) data_get($approved, 'total_amount', 0),
            ],
            'overdue_receivables' => [
                'count' => (int) ($overdue->total_count ?? 0),
                'amount' => (float) ($overdue->total_amount ?? 0),
            ],
            'cash_differences' => [
                'count' => (int) ($cashDifference->total_count ?? 0),
                'amount' => (float) ($cashDifference->total_amount ?? 0),
            ],
            'ledger_integrity' => [
                'cash_payment_without_ledger' => $cashPaymentsWithoutLedger,
                'cash_refund_without_ledger' => $cashRefundsWithoutLedger,
            ],
        ];
    }

    private function percentageChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return abs($current) < 0.005 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
