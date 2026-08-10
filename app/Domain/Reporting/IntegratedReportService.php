<?php

namespace App\Domain\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * @phpstan-type ReportFilters array{
 *     from: CarbonImmutable,
 *     to: CarbonImmutable,
 *     branch_id: int|null,
 *     report: string,
 *     status: string,
 *     payment_method_id: int|null,
 *     category_id: int|null,
 *     search: string
 * }
 */
class IntegratedReportService
{
    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array<string, mixed>
     */
    public function generate(int $companyId, array $branchIds, array $filters): array
    {
        $scopedBranchIds = $filters['branch_id'] === null
            ? $branchIds
            : array_values(array_intersect($branchIds, [$filters['branch_id']]));

        $dataset = match ($filters['report']) {
            'finance' => $this->financeDataset($scopedBranchIds, $filters),
            'receivables' => $this->receivableDataset($scopedBranchIds, $filters),
            'cash' => $this->cashDataset($scopedBranchIds, $filters),
            'assets' => $this->assetDataset($scopedBranchIds, $filters),
            'transfers' => $this->transferDataset($scopedBranchIds, $filters),
            'inventory-audits' => $this->inventoryAuditDataset($scopedBranchIds, $filters),
            default => $this->operationalDataset($scopedBranchIds, $filters),
        };

        return [
            'summary' => $this->summary($scopedBranchIds, $filters),
            'trend' => $this->trend($scopedBranchIds, $filters['from'], $filters['to']),
            'branchPerformance' => $this->branchPerformance(
                $companyId,
                $scopedBranchIds,
                $filters['from'],
                $filters['to'],
            ),
            'columns' => $dataset['columns'],
            'rows' => $dataset['rows'],
            'statusOptions' => $this->statusOptions($filters['report']),
            'reportMeta' => [
                'key' => $filters['report'],
                'label' => $this->reportLabel($filters['report']),
                'description' => $this->reportDescription($filters['report']),
                'row_count' => count($dataset['rows']),
            ],
            'methodology' => [
                'cash_flow' => 'Payment completed masuk dikurangi payment completed keluar dan refund paid. Payment void dan refund selain paid tidak memengaruhi arus kas.',
                'receivables' => 'Piutang menampilkan saldo rental positif sampai akhir periode dan mengecualikan draft, cancelled, void, serta rejected.',
                'rental_value' => 'Nilai rental memakai total_amount pada rental yang checkout/tercatat dalam periode dan tidak berstatus draft, cancelled, void, atau rejected.',
                'deposit' => 'Deposit ditampilkan terpisah dari penerimaan rental dan saldo deposit held dihitung dari payment deposit completed dikurangi refund deposit paid.',
                'branch_scope' => 'Laporan konsolidasi hanya menggunakan cabang yang dapat diakses pengguna. Pengguna branch-scoped otomatis terkunci pada cabang aktif.',
            ],
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return list<array<string, mixed>>
     */
    private function summary(array $branchIds, array $filters): array
    {
        if ($branchIds === []) {
            return $this->emptySummary();
        }

        $from = $filters['from'];
        $to = $filters['to'];
        $rentals = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->whereBetween(DB::raw('COALESCE(checked_out_at, created_at)'), [$from, $to])
            ->selectRaw('COUNT(*) as rental_count, COALESCE(SUM(total_amount), 0) as rental_value')
            ->selectRaw('COALESCE(SUM(late_fee_amount + damage_fee_amount), 0) as charges')
            ->first();
        $payments = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outflows")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' AND type = 'deposit' THEN amount ELSE 0 END), 0) as deposits")
            ->first();
        $refunds = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('COUNT(*) as refund_count, COALESCE(SUM(amount), 0) as refund_amount')
            ->first();
        $receivables = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where('balance_due', '>', 0)
            ->where('created_at', '<=', $to)
            ->selectRaw('COUNT(*) as rental_count, COALESCE(SUM(balance_due), 0) as balance_due')
            ->first();
        $maintenance = DB::table('maintenance_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('reported_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'void'])
            ->sum('actual_cost');
        $depositPayments = (float) DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->where('type', 'deposit')
            ->where('paid_at', '<=', $to)
            ->sum('amount');
        $depositRefunds = (float) DB::table('refunds')
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->whereIn('refunds.branch_id', $branchIds)
            ->where('refunds.status', 'paid')
            ->where('payments.type', 'deposit')
            ->where('refunds.processed_at', '<=', $to)
            ->sum('refunds.amount');

        $collections = (float) ($payments->collections ?? 0);
        $outflows = (float) ($payments->outflows ?? 0);
        $refundAmount = (float) ($refunds->refund_amount ?? 0);

        return [
            $this->summaryCard('rentals', 'Rental tercatat', (int) ($rentals->rental_count ?? 0), 'number', 'Rental valid pada periode.'),
            $this->summaryCard('rental_value', 'Nilai rental', (float) ($rentals->rental_value ?? 0), 'money', 'Total nilai kontrak rental pada periode.'),
            $this->summaryCard('collections', 'Payment masuk', $collections, 'money', 'Payment completed berarah masuk.'),
            $this->summaryCard('net_cash_flow', 'Arus kas bersih', $collections - $outflows - $refundAmount, 'money', 'Masuk dikurangi keluar dan refund paid.'),
            $this->summaryCard('receivables', 'Piutang berjalan', (float) ($receivables->balance_due ?? 0), 'money', sprintf('%d rental masih memiliki saldo.', (int) ($receivables->rental_count ?? 0))),
            $this->summaryCard('refunds', 'Refund paid', $refundAmount, 'money', sprintf('%d refund dibayarkan.', (int) ($refunds->refund_count ?? 0))),
            $this->summaryCard('deposit_held', 'Deposit ditahan', max(0, $depositPayments - $depositRefunds), 'money', sprintf('Penerimaan deposit periode: Rp %s.', number_format((float) ($payments->deposits ?? 0), 0, ',', '.'))),
            $this->summaryCard('maintenance', 'Biaya maintenance', (float) $maintenance, 'money', sprintf('Denda/kerusakan rental: Rp %s.', number_format((float) ($rentals->charges ?? 0), 0, ',', '.'))),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function emptySummary(): array
    {
        return [
            $this->summaryCard('rentals', 'Rental tercatat', 0, 'number', 'Rental valid pada periode.'),
            $this->summaryCard('rental_value', 'Nilai rental', 0, 'money', 'Total nilai kontrak rental pada periode.'),
            $this->summaryCard('collections', 'Payment masuk', 0, 'money', 'Payment completed berarah masuk.'),
            $this->summaryCard('net_cash_flow', 'Arus kas bersih', 0, 'money', 'Masuk dikurangi keluar dan refund paid.'),
            $this->summaryCard('receivables', 'Piutang berjalan', 0, 'money', 'Tidak ada piutang dalam cakupan.'),
            $this->summaryCard('refunds', 'Refund paid', 0, 'money', 'Tidak ada refund paid pada periode.'),
            $this->summaryCard('deposit_held', 'Deposit ditahan', 0, 'money', 'Tidak ada deposit dalam cakupan.'),
            $this->summaryCard('maintenance', 'Biaya maintenance', 0, 'money', 'Tidak ada biaya maintenance pada periode.'),
        ];
    }

    /** @return array<string, mixed> */
    private function summaryCard(string $key, string $label, int|float $value, string $type, string $note): array
    {
        return compact('key', 'label', 'value', 'type', 'note');
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    private function trend(array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $monthly = $from->startOfDay()->diffInDays($to->startOfDay()) > 62;
        $buckets = [];
        $cursor = $monthly ? $from->startOfMonth() : $from->startOfDay();
        $last = $monthly ? $to->startOfMonth() : $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] = [
                'key' => $key,
                'label' => $monthly ? $cursor->format('M Y') : $cursor->format('d M'),
                'rental_value' => 0.0,
                'collections' => 0.0,
                'outflows' => 0.0,
                'net' => 0.0,
            ];
            $cursor = $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        if ($branchIds === []) {
            return array_values($buckets);
        }

        /** @var Collection<int, stdClass> $rentals */
        $rentals = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->whereBetween(DB::raw('COALESCE(checked_out_at, created_at)'), [$from, $to])
            ->selectRaw('DATE(COALESCE(checked_out_at, created_at)) as occurred_on, SUM(total_amount) as total')
            ->groupBy('occurred_on')
            ->get();

        foreach ($rentals as $row) {
            $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'rental_value', (float) $row->total);
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
            $amount = (float) $row->total;
            if ((string) $row->direction === 'in') {
                $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'collections', $amount);
                $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'net', $amount);
            } else {
                $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'outflows', $amount);
                $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'net', -$amount);
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
            $amount = (float) $row->total;
            $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'outflows', $amount);
            $this->addTrendValue($buckets, (string) $row->occurred_on, $monthly, 'net', -$amount);
        }

        return array_values($buckets);
    }

    /** @param array<string, array<string, mixed>> $buckets */
    private function addTrendValue(array &$buckets, string $date, bool $monthly, string $field, float $amount): void
    {
        $parsed = CarbonImmutable::parse($date);
        $key = $monthly ? $parsed->format('Y-m') : $parsed->toDateString();

        if (isset($buckets[$key])) {
            $buckets[$key][$field] = (float) $buckets[$key][$field] + $amount;
        }
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    private function branchPerformance(int $companyId, array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $branches */
        $branches = DB::table('branches')
            ->where('company_id', $companyId)
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $rentals = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->whereBetween(DB::raw('COALESCE(checked_out_at, created_at)'), [$from, $to])
            ->selectRaw('branch_id, COUNT(*) as rental_count, COALESCE(SUM(total_amount), 0) as rental_value')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $payments = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("branch_id, COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as collections")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outflows")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');
        $refunds = DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->selectRaw('branch_id, COALESCE(SUM(amount), 0) as refunds')
            ->groupBy('branch_id')
            ->pluck('refunds', 'branch_id');
        $receivables = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where('balance_due', '>', 0)
            ->where('created_at', '<=', $to)
            ->selectRaw('branch_id, COALESCE(SUM(balance_due), 0) as receivables')
            ->groupBy('branch_id')
            ->pluck('receivables', 'branch_id');
        $maintenance = DB::table('maintenance_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('reported_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'void'])
            ->selectRaw('branch_id, COALESCE(SUM(actual_cost), 0) as cost')
            ->groupBy('branch_id')
            ->pluck('cost', 'branch_id');

        return array_values($branches->map(static function (stdClass $branch) use ($rentals, $payments, $refunds, $receivables, $maintenance): array {
            $rental = $rentals->get($branch->id);
            $payment = $payments->get($branch->id);
            $collections = (float) ($payment->collections ?? 0);
            $outflows = (float) ($payment->outflows ?? 0);
            $refundAmount = (float) $refunds->get($branch->id, 0);

            return [
                'id' => (int) $branch->id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'rental_count' => (int) ($rental->rental_count ?? 0),
                'rental_value' => (float) ($rental->rental_value ?? 0),
                'collections' => $collections,
                'outflows' => $outflows + $refundAmount,
                'net' => $collections - $outflows - $refundAmount,
                'receivables' => (float) $receivables->get($branch->id, 0),
                'maintenance_cost' => (float) $maintenance->get($branch->id, 0),
            ];
        })->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function operationalDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('kind', 'Jenis', 'text', 10),
            $this->column('number', 'Nomor', 'text', 17),
            $this->column('reference', 'Referensi', 'text', 16),
            $this->column('customer', 'Pelanggan', 'text', 18),
            $this->column('branch', 'Cabang', 'text', 10),
            $this->column('status', 'Status', 'status', 11),
            $this->column('occurred_at', 'Waktu Transaksi', 'datetime', 17),
            $this->column('starts_at', 'Mulai/Tempo', 'datetime', 16),
            $this->column('ends_at', 'Selesai/Kembali', 'datetime', 16),
            $this->column('total_amount', 'Nilai', 'money', 14),
            $this->column('charges', 'Denda/Biaya', 'money', 13),
            $this->column('paid_amount', 'Terbayar', 'money', 13),
            $this->column('balance_due', 'Saldo', 'money', 13),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $bookingQuery = DB::table('bookings as bookings')
            ->join('branches as branches', 'branches.id', '=', 'bookings.branch_id')
            ->join('customers as customers', 'customers.id', '=', 'bookings.customer_id')
            ->whereIn('bookings.branch_id', $branchIds)
            ->whereNull('bookings.deleted_at')
            ->whereBetween('bookings.booked_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('bookings.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('bookings.booking_number', 'like', "%{$search}%")
                        ->orWhere('bookings.legacy_number', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('customers.phone', 'like', "%{$search}%");
                });
            })
            ->select([
                'bookings.id', 'bookings.booking_number', 'bookings.status',
                'bookings.booked_at', 'bookings.starts_at', 'bookings.ends_at',
                'bookings.total_amount', 'bookings.deposit_paid',
                'customers.name as customer_name', 'branches.code as branch_code',
            ]);

        /** @var Collection<int, stdClass> $bookingRecords */
        $bookingRecords = $bookingQuery->get();
        $bookings = $bookingRecords->map(static fn (stdClass $row): array => [
            'id' => 'booking-'.(int) $row->id,
            'href' => '/bookings/'.(int) $row->id,
            'sort_at' => (string) $row->booked_at,
            'values' => [
                'kind' => 'Booking',
                'number' => (string) $row->booking_number,
                'reference' => null,
                'customer' => (string) $row->customer_name,
                'branch' => (string) $row->branch_code,
                'status' => (string) $row->status,
                'occurred_at' => (string) $row->booked_at,
                'starts_at' => (string) $row->starts_at,
                'ends_at' => (string) $row->ends_at,
                'total_amount' => (float) $row->total_amount,
                'charges' => 0.0,
                'paid_amount' => (float) $row->deposit_paid,
                'balance_due' => max(0, (float) $row->total_amount - (float) $row->deposit_paid),
            ],
        ]);

        $rentalQuery = DB::table('rentals as rentals')
            ->join('branches as branches', 'branches.id', '=', 'rentals.branch_id')
            ->join('customers as customers', 'customers.id', '=', 'rentals.customer_id')
            ->leftJoin('bookings as bookings', 'bookings.id', '=', 'rentals.booking_id')
            ->whereIn('rentals.branch_id', $branchIds)
            ->whereNull('rentals.deleted_at')
            ->whereBetween(DB::raw('COALESCE(rentals.checked_out_at, rentals.created_at)'), [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('rentals.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('rentals.rental_number', 'like', "%{$search}%")
                        ->orWhere('rentals.legacy_number', 'like', "%{$search}%")
                        ->orWhere('bookings.booking_number', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('customers.phone', 'like', "%{$search}%");
                });
            })
            ->select([
                'rentals.id',
                'rentals.rental_number',
                'bookings.booking_number',
                'customers.name as customer_name',
                'branches.code as branch_code',
                'rentals.status',
                'rentals.created_at',
                'rentals.checked_out_at',
                'rentals.due_at',
                'rentals.returned_at',
                'rentals.total_amount',
                'rentals.late_fee_amount',
                'rentals.damage_fee_amount',
                'rentals.paid_amount',
                'rentals.balance_due',
            ])
            ->orderByDesc(DB::raw('COALESCE(rentals.checked_out_at, rentals.created_at)'));

        /** @var Collection<int, stdClass> $rentalRecords */
        $rentalRecords = $rentalQuery->get();
        $rentals = $rentalRecords->map(static fn (stdClass $row): array => [
            'id' => 'rental-'.(int) $row->id,
            'href' => '/rentals/'.(int) $row->id,
            'sort_at' => (string) ($row->checked_out_at ?? $row->created_at),
            'values' => [
                'kind' => 'Rental',
                'number' => (string) $row->rental_number,
                'reference' => $row->booking_number === null ? null : (string) $row->booking_number,
                'customer' => (string) $row->customer_name,
                'branch' => (string) $row->branch_code,
                'status' => (string) $row->status,
                'occurred_at' => (string) ($row->checked_out_at ?? $row->created_at),
                'starts_at' => (string) $row->due_at,
                'ends_at' => $row->returned_at === null ? null : (string) $row->returned_at,
                'total_amount' => (float) $row->total_amount,
                'charges' => (float) $row->late_fee_amount + (float) $row->damage_fee_amount,
                'paid_amount' => (float) $row->paid_amount,
                'balance_due' => (float) $row->balance_due,
            ],
        ]);

        $returnQuery = DB::table('rental_returns as returns')
            ->join('rentals as rentals', 'rentals.id', '=', 'returns.rental_id')
            ->join('branches as branches', 'branches.id', '=', 'returns.branch_id')
            ->join('customers as customers', 'customers.id', '=', 'rentals.customer_id')
            ->whereIn('returns.branch_id', $branchIds)
            ->whereBetween('returns.returned_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('returns.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('returns.return_number', 'like', "%{$search}%")
                        ->orWhere('rentals.rental_number', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'returns.id', 'returns.rental_id', 'returns.return_number', 'returns.status',
                'returns.returned_at', 'returns.total_charge_amount',
                'returns.late_fee_amount', 'returns.damage_fee_amount',
                'rentals.rental_number', 'rentals.total_amount', 'rentals.paid_amount',
                'rentals.balance_due', 'customers.name as customer_name',
                'branches.code as branch_code',
            ]);

        /** @var Collection<int, stdClass> $returnRecords */
        $returnRecords = $returnQuery->get();
        $returns = $returnRecords->map(static fn (stdClass $row): array => [
            'id' => 'return-'.(int) $row->id,
            'href' => '/rentals/'.(int) $row->rental_id,
            'sort_at' => (string) $row->returned_at,
            'values' => [
                'kind' => 'Return',
                'number' => (string) $row->return_number,
                'reference' => (string) $row->rental_number,
                'customer' => (string) $row->customer_name,
                'branch' => (string) $row->branch_code,
                'status' => (string) $row->status,
                'occurred_at' => (string) $row->returned_at,
                'starts_at' => null,
                'ends_at' => (string) $row->returned_at,
                'total_amount' => (float) $row->total_amount,
                'charges' => (float) $row->total_charge_amount,
                'paid_amount' => (float) $row->paid_amount,
                'balance_due' => (float) $row->balance_due,
            ],
        ]);

        $rows = array_values(
            $bookings->concat($rentals)->concat($returns)
                ->sortByDesc('sort_at')
                ->map(static function (array $row): array {
                    unset($row['sort_at']);

                    return $row;
                })
                ->values()
                ->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function financeDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('kind', 'Jenis', 'text', 11),
            $this->column('number', 'Nomor', 'text', 18),
            $this->column('branch', 'Cabang', 'text', 9),
            $this->column('customer', 'Pelanggan', 'text', 17),
            $this->column('source', 'Sumber', 'text', 15),
            $this->column('method', 'Metode', 'text', 13),
            $this->column('category', 'Kategori', 'text', 15),
            $this->column('direction', 'Arah', 'direction', 8),
            $this->column('status', 'Status', 'status', 10),
            $this->column('occurred_at', 'Waktu', 'datetime', 17),
            $this->column('amount', 'Jumlah', 'money', 14),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $paymentQuery = DB::table('payments as payments')
            ->join('branches as branches', 'branches.id', '=', 'payments.branch_id')
            ->join('payment_methods as methods', 'methods.id', '=', 'payments.payment_method_id')
            ->leftJoin('financial_categories as categories', 'categories.id', '=', 'payments.financial_category_id')
            ->leftJoin('customers as customers', 'customers.id', '=', 'payments.customer_id')
            ->whereIn('payments.branch_id', $branchIds)
            ->whereBetween('payments.paid_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('payments.status', $filters['status']))
            ->when($filters['payment_method_id'] !== null, fn ($builder) => $builder->where('payments.payment_method_id', $filters['payment_method_id']))
            ->when($filters['category_id'] !== null, fn ($builder) => $builder->where('payments.financial_category_id', $filters['category_id']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('payments.payment_number', 'like', "%{$search}%")
                        ->orWhere('payments.external_reference', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'payments.id', 'payments.payment_number', 'payments.direction', 'payments.type',
                'payments.source_context', 'payments.status', 'payments.amount', 'payments.paid_at',
                'branches.code as branch_code', 'customers.name as customer_name',
                'methods.name as method_name', 'categories.name as category_name',
            ]);

        /** @var Collection<int, stdClass> $paymentRecords */
        $paymentRecords = $paymentQuery->get();
        $payments = $paymentRecords->map(static fn (stdClass $row): array => [
            'id' => 'payment-'.(int) $row->id,
            'href' => '/finance/payments/'.(int) $row->id,
            'sort_at' => (string) $row->paid_at,
            'values' => [
                'kind' => 'Payment',
                'number' => (string) $row->payment_number,
                'branch' => (string) $row->branch_code,
                'customer' => $row->customer_name === null ? null : (string) $row->customer_name,
                'source' => (string) ($row->source_context ?? $row->type),
                'method' => (string) $row->method_name,
                'category' => $row->category_name === null ? null : (string) $row->category_name,
                'direction' => (string) $row->direction,
                'status' => (string) $row->status,
                'occurred_at' => (string) $row->paid_at,
                'amount' => (float) $row->amount,
            ],
        ]);

        $refundQuery = DB::table('refunds as refunds')
            ->join('branches as branches', 'branches.id', '=', 'refunds.branch_id')
            ->join('payment_methods as methods', 'methods.id', '=', 'refunds.payment_method_id')
            ->leftJoin('payments as payments', 'payments.id', '=', 'refunds.payment_id')
            ->leftJoin('customers as customers', 'customers.id', '=', 'payments.customer_id')
            ->whereIn('refunds.branch_id', $branchIds)
            ->whereBetween(DB::raw('COALESCE(refunds.processed_at, refunds.created_at)'), [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('refunds.status', $filters['status']))
            ->when($filters['payment_method_id'] !== null, fn ($builder) => $builder->where('refunds.payment_method_id', $filters['payment_method_id']))
            ->when($filters['category_id'] !== null, fn ($builder) => $builder->whereRaw('1 = 0'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('refunds.refund_number', 'like', "%{$search}%")
                        ->orWhere('refunds.external_reference', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'refunds.id', 'refunds.refund_number', 'refunds.status', 'refunds.amount',
                'refunds.processed_at', 'refunds.created_at', 'branches.code as branch_code',
                'customers.name as customer_name', 'methods.name as method_name',
            ]);

        /** @var Collection<int, stdClass> $refundRecords */
        $refundRecords = $refundQuery->get();
        $refunds = $refundRecords->map(static function (stdClass $row): array {
            $occurredAt = (string) ($row->processed_at ?? $row->created_at);

            return [
                'id' => 'refund-'.(int) $row->id,
                'href' => '/finance/refunds/'.(int) $row->id,
                'sort_at' => $occurredAt,
                'values' => [
                    'kind' => 'Refund',
                    'number' => (string) $row->refund_number,
                    'branch' => (string) $row->branch_code,
                    'customer' => $row->customer_name === null ? null : (string) $row->customer_name,
                    'source' => 'refund',
                    'method' => (string) $row->method_name,
                    'category' => null,
                    'direction' => 'out',
                    'status' => (string) $row->status,
                    'occurred_at' => $occurredAt,
                    'amount' => (float) $row->amount,
                ],
            ];
        });

        $adjustmentQuery = DB::table('rental_financial_adjustments as adjustments')
            ->join('rentals as rentals', 'rentals.id', '=', 'adjustments.rental_id')
            ->join('branches as branches', 'branches.id', '=', 'adjustments.branch_id')
            ->join('customers as customers', 'customers.id', '=', 'rentals.customer_id')
            ->whereIn('adjustments.branch_id', $branchIds)
            ->whereBetween('adjustments.created_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all' && $filters['status'] !== 'posted', fn ($builder) => $builder->whereRaw('1 = 0'))
            ->when($filters['payment_method_id'] !== null || $filters['category_id'] !== null, fn ($builder) => $builder->whereRaw('1 = 0'))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('adjustments.adjustment_number', 'like', "%{$search}%")
                        ->orWhere('adjustments.reason', 'like', "%{$search}%")
                        ->orWhere('rentals.rental_number', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'adjustments.id', 'adjustments.rental_id', 'adjustments.adjustment_number',
                'adjustments.component', 'adjustments.direction', 'adjustments.amount',
                'adjustments.created_at', 'branches.code as branch_code',
                'customers.name as customer_name', 'rentals.rental_number',
            ]);

        /** @var Collection<int, stdClass> $adjustmentRecords */
        $adjustmentRecords = $adjustmentQuery->get();
        $adjustments = $adjustmentRecords->map(static fn (stdClass $row): array => [
            'id' => 'adjustment-'.(int) $row->id,
            'href' => '/rentals/'.(int) $row->rental_id,
            'sort_at' => (string) $row->created_at,
            'values' => [
                'kind' => 'Adjustment',
                'number' => (string) $row->adjustment_number,
                'branch' => (string) $row->branch_code,
                'customer' => (string) $row->customer_name,
                'source' => sprintf('%s · %s', (string) $row->rental_number, (string) $row->component),
                'method' => null,
                'category' => null,
                'direction' => (string) $row->direction,
                'status' => 'posted',
                'occurred_at' => (string) $row->created_at,
                'amount' => (float) $row->amount,
            ],
        ]);

        $rows = array_values(
            $payments->concat($refunds)->concat($adjustments)
                ->sortByDesc('sort_at')
                ->map(static function (array $row): array {
                    unset($row['sort_at']);

                    return $row;
                })
                ->values()
                ->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function receivableDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('rental_number', 'No. Rental', 'text', 17),
            $this->column('customer', 'Pelanggan', 'text', 20),
            $this->column('branch', 'Cabang', 'text', 9),
            $this->column('status', 'Status', 'status', 11),
            $this->column('due_at', 'Jatuh Tempo', 'datetime', 17),
            $this->column('days_overdue', 'Hari Lewat', 'number', 10),
            $this->column('total_amount', 'Nilai Rental', 'money', 15),
            $this->column('paid_amount', 'Terbayar', 'money', 15),
            $this->column('balance_due', 'Piutang', 'money', 15),
            $this->column('deposit_amount', 'Deposit', 'money', 15),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $query = DB::table('rentals as rentals')
            ->join('branches as branches', 'branches.id', '=', 'rentals.branch_id')
            ->join('customers as customers', 'customers.id', '=', 'rentals.customer_id')
            ->whereIn('rentals.branch_id', $branchIds)
            ->whereNull('rentals.deleted_at')
            ->whereNotIn('rentals.status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where('rentals.balance_due', '>', 0)
            ->where('rentals.created_at', '<=', $filters['to'])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('rentals.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('rentals.rental_number', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('customers.phone', 'like', "%{$search}%");
                });
            })
            ->select([
                'rentals.id', 'rentals.rental_number', 'rentals.status', 'rentals.due_at',
                'rentals.total_amount', 'rentals.paid_amount', 'rentals.balance_due',
                'rentals.deposit_amount', 'customers.name as customer_name',
                'branches.code as branch_code',
            ])
            ->orderBy('rentals.due_at');

        /** @var Collection<int, stdClass> $records */
        $records = $query->get();
        $to = $filters['to'];
        $rows = array_values(
            $records->map(static function (stdClass $row) use ($to): array {
                $dueAt = CarbonImmutable::parse((string) $row->due_at);
                $daysOverdue = $dueAt->lessThan($to)
                    ? (int) $dueAt->startOfDay()->diffInDays($to->startOfDay())
                    : 0;

                return [
                    'id' => 'receivable-'.(int) $row->id,
                    'href' => '/rentals/'.(int) $row->id,
                    'values' => [
                        'rental_number' => (string) $row->rental_number,
                        'customer' => (string) $row->customer_name,
                        'branch' => (string) $row->branch_code,
                        'status' => (string) $row->status,
                        'due_at' => (string) $row->due_at,
                        'days_overdue' => $daysOverdue,
                        'total_amount' => (float) $row->total_amount,
                        'paid_amount' => (float) $row->paid_amount,
                        'balance_due' => (float) $row->balance_due,
                        'deposit_amount' => (float) $row->deposit_amount,
                    ],
                ];
            })->values()->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function cashDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('register', 'Register', 'text', 18),
            $this->column('branch', 'Cabang', 'text', 9),
            $this->column('cashier', 'Kasir', 'text', 17),
            $this->column('status', 'Status', 'status', 9),
            $this->column('opened_at', 'Dibuka', 'datetime', 17),
            $this->column('closed_at', 'Ditutup', 'datetime', 17),
            $this->column('opening_balance', 'Saldo Awal', 'money', 14),
            $this->column('incoming', 'Kas Masuk', 'money', 14),
            $this->column('outgoing', 'Kas Keluar', 'money', 14),
            $this->column('expected', 'Ekspektasi', 'money', 14),
            $this->column('actual', 'Aktual', 'money', 14),
            $this->column('difference', 'Selisih', 'money', 13),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $transactions = DB::table('cash_transactions')
            ->selectRaw("cash_session_id, COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as incoming")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as outgoing")
            ->groupBy('cash_session_id');
        $query = DB::table('cash_sessions as sessions')
            ->join('cash_registers as registers', 'registers.id', '=', 'sessions.cash_register_id')
            ->join('branches as branches', 'branches.id', '=', 'registers.branch_id')
            ->join('users as users', 'users.id', '=', 'sessions.opened_by')
            ->leftJoinSub($transactions, 'transactions', static fn ($join) => $join->on('transactions.cash_session_id', '=', 'sessions.id'))
            ->whereIn('registers.branch_id', $branchIds)
            ->whereBetween('sessions.opened_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('sessions.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('registers.name', 'like', "%{$search}%")
                        ->orWhere('registers.code', 'like', "%{$search}%")
                        ->orWhere('users.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'sessions.id', 'sessions.status', 'sessions.opened_at', 'sessions.closed_at',
                'sessions.opening_balance', 'sessions.expected_closing_balance',
                'sessions.actual_closing_balance', 'sessions.difference_amount',
                'registers.code as register_code', 'registers.name as register_name',
                'branches.code as branch_code', 'users.name as cashier_name',
                'transactions.incoming', 'transactions.outgoing',
            ])
            ->orderByDesc('sessions.opened_at');

        /** @var Collection<int, stdClass> $records */
        $records = $query->get();
        $rows = array_values(
            $records->map(static fn (stdClass $row): array => [
                'id' => 'cash-session-'.(int) $row->id,
                'href' => '/finance/master-data',
                'values' => [
                    'register' => sprintf('%s · %s', (string) $row->register_code, (string) $row->register_name),
                    'branch' => (string) $row->branch_code,
                    'cashier' => (string) $row->cashier_name,
                    'status' => (string) $row->status,
                    'opened_at' => (string) $row->opened_at,
                    'closed_at' => $row->closed_at === null ? null : (string) $row->closed_at,
                    'opening_balance' => (float) $row->opening_balance,
                    'incoming' => (float) ($row->incoming ?? 0),
                    'outgoing' => (float) ($row->outgoing ?? 0),
                    'expected' => (float) $row->expected_closing_balance,
                    'actual' => $row->actual_closing_balance === null ? null : (float) $row->actual_closing_balance,
                    'difference' => (float) $row->difference_amount,
                ],
            ])->values()->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function assetDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('asset_code', 'Kode Aset', 'text', 15),
            $this->column('product', 'Produk', 'text', 23),
            $this->column('category', 'Kategori', 'text', 15),
            $this->column('branch', 'Cabang', 'text', 9),
            $this->column('status', 'Status', 'status', 11),
            $this->column('condition', 'Kondisi', 'status', 10),
            $this->column('purchase_price', 'Harga Beli', 'money', 15),
            $this->column('maintenance_count', 'Jml. Service', 'number', 11),
            $this->column('maintenance_cost', 'Biaya Service', 'money', 15),
            $this->column('last_maintenance_at', 'Service Terakhir', 'datetime', 17),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $maintenance = DB::table('maintenance_orders')
            ->whereBetween('reported_at', [$filters['from'], $filters['to']])
            ->whereNotIn('status', ['cancelled', 'void'])
            ->selectRaw('asset_id, COUNT(*) as maintenance_count, COALESCE(SUM(actual_cost), 0) as maintenance_cost, MAX(COALESCE(completed_at, reported_at)) as last_maintenance_at')
            ->groupBy('asset_id');
        $query = DB::table('assets as assets')
            ->join('products as products', 'products.id', '=', 'assets.product_id')
            ->join('branches as branches', 'branches.id', '=', 'assets.current_branch_id')
            ->leftJoin('product_categories as categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($maintenance, 'maintenance', static fn ($join) => $join->on('maintenance.asset_id', '=', 'assets.id'))
            ->whereIn('assets.current_branch_id', $branchIds)
            ->whereNull('assets.deleted_at')
            ->whereNull('products.deleted_at')
            ->where('assets.created_at', '<=', $filters['to'])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('assets.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('assets.asset_code', 'like', "%{$search}%")
                        ->orWhere('assets.serial_number', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('products.sku', 'like', "%{$search}%");
                });
            })
            ->select([
                'assets.id', 'assets.asset_code', 'assets.status', 'assets.condition',
                'assets.purchase_price', 'products.name as product_name',
                'categories.name as category_name', 'branches.code as branch_code',
                'maintenance.maintenance_count', 'maintenance.maintenance_cost',
                'maintenance.last_maintenance_at',
            ])
            ->orderByDesc(DB::raw('COALESCE(maintenance.maintenance_cost, 0)'))
            ->orderBy('products.name');

        /** @var Collection<int, stdClass> $records */
        $records = $query->get();
        $rows = array_values(
            $records->map(static fn (stdClass $row): array => [
                'id' => 'asset-'.(int) $row->id,
                'href' => '/reports/asset-analytics?search='.rawurlencode((string) $row->asset_code),
                'values' => [
                    'asset_code' => (string) $row->asset_code,
                    'product' => (string) $row->product_name,
                    'category' => $row->category_name === null ? null : (string) $row->category_name,
                    'branch' => (string) $row->branch_code,
                    'status' => (string) $row->status,
                    'condition' => (string) $row->condition,
                    'purchase_price' => (float) $row->purchase_price,
                    'maintenance_count' => (int) ($row->maintenance_count ?? 0),
                    'maintenance_cost' => (float) ($row->maintenance_cost ?? 0),
                    'last_maintenance_at' => $row->last_maintenance_at === null ? null : (string) $row->last_maintenance_at,
                ],
            ])->values()->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function transferDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('transfer_number', 'No. Transfer', 'text', 18),
            $this->column('origin', 'Asal', 'text', 13),
            $this->column('destination', 'Tujuan', 'text', 13),
            $this->column('status', 'Status', 'status', 12),
            $this->column('requested_at', 'Diajukan', 'datetime', 17),
            $this->column('shipped_at', 'Dikirim', 'datetime', 17),
            $this->column('received_at', 'Diterima', 'datetime', 17),
            $this->column('item_count', 'Unit', 'number', 8),
            $this->column('expense_total', 'Biaya Aktual', 'money', 15),
            $this->column('discrepancies', 'Discrepancy', 'number', 11),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $items = DB::table('branch_transfer_items')
            ->selectRaw('branch_transfer_id, COALESCE(SUM(quantity), 0) as item_count, SUM(CASE WHEN discrepancy_type IS NOT NULL THEN 1 ELSE 0 END) as discrepancies')
            ->groupBy('branch_transfer_id');
        $expenses = DB::table('branch_transfer_expenses')
            ->where('status', 'paid')
            ->selectRaw('branch_transfer_id, COALESCE(SUM(actual_amount), 0) as expense_total')
            ->groupBy('branch_transfer_id');
        $query = DB::table('branch_transfers as transfers')
            ->join('branches as origins', 'origins.id', '=', 'transfers.from_branch_id')
            ->join('branches as destinations', 'destinations.id', '=', 'transfers.to_branch_id')
            ->leftJoinSub($items, 'items', static fn ($join) => $join->on('items.branch_transfer_id', '=', 'transfers.id'))
            ->leftJoinSub($expenses, 'expenses', static fn ($join) => $join->on('expenses.branch_transfer_id', '=', 'transfers.id'))
            ->where(function ($builder) use ($branchIds): void {
                $builder->whereIn('transfers.from_branch_id', $branchIds)
                    ->orWhereIn('transfers.to_branch_id', $branchIds);
            })
            ->whereBetween('transfers.created_at', [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('transfers.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('transfers.transfer_number', 'like', "%{$search}%")
                        ->orWhere('transfers.tracking_number', 'like', "%{$search}%")
                        ->orWhere('transfers.waybill_number', 'like', "%{$search}%")
                        ->orWhere('origins.name', 'like', "%{$search}%")
                        ->orWhere('destinations.name', 'like', "%{$search}%");
                });
            })
            ->select([
                'transfers.id', 'transfers.transfer_number', 'transfers.status',
                'transfers.requested_at', 'transfers.shipped_at', 'transfers.received_at',
                'origins.code as origin_code', 'destinations.code as destination_code',
                'items.item_count', 'items.discrepancies', 'expenses.expense_total',
            ])
            ->orderByDesc('transfers.created_at');

        /** @var Collection<int, stdClass> $records */
        $records = $query->get();
        $rows = array_values(
            $records->map(static fn (stdClass $row): array => [
                'id' => 'transfer-'.(int) $row->id,
                'href' => '/transfers/'.(int) $row->id,
                'values' => [
                    'transfer_number' => (string) $row->transfer_number,
                    'origin' => (string) $row->origin_code,
                    'destination' => (string) $row->destination_code,
                    'status' => (string) $row->status,
                    'requested_at' => $row->requested_at === null ? null : (string) $row->requested_at,
                    'shipped_at' => $row->shipped_at === null ? null : (string) $row->shipped_at,
                    'received_at' => $row->received_at === null ? null : (string) $row->received_at,
                    'item_count' => (int) ($row->item_count ?? 0),
                    'expense_total' => (float) ($row->expense_total ?? 0),
                    'discrepancies' => (int) ($row->discrepancies ?? 0),
                ],
            ])->values()->all(),
        );

        return compact('columns', 'rows');
    }

    /**
     * @param  list<int>  $branchIds
     * @param  ReportFilters  $filters
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    private function inventoryAuditDataset(array $branchIds, array $filters): array
    {
        $columns = [
            $this->column('audit_number', 'No. Audit', 'text', 18),
            $this->column('title', 'Judul', 'text', 24),
            $this->column('branch', 'Cabang', 'text', 11),
            $this->column('status', 'Status', 'status', 12),
            $this->column('scheduled_at', 'Jadwal', 'datetime', 17),
            $this->column('started_at', 'Dimulai', 'datetime', 17),
            $this->column('submitted_at', 'Diajukan', 'datetime', 17),
            $this->column('approved_at', 'Disetujui', 'datetime', 17),
            $this->column('closed_at', 'Ditutup', 'datetime', 17),
            $this->column('snapshot_items', 'Snapshot', 'number', 9),
            $this->column('counted_items', 'Dihitung', 'number', 9),
            $this->column('findings', 'Temuan', 'number', 9),
            $this->column('unresolved', 'Belum Selesai', 'number', 12),
        ];

        if ($branchIds === []) {
            return compact('columns') + ['rows' => []];
        }

        $items = DB::table('inventory_audit_items')
            ->selectRaw('inventory_audit_id, COUNT(*) as snapshot_items')
            ->selectRaw("SUM(CASE WHEN finding_status <> 'pending' THEN 1 ELSE 0 END) as counted_items")
            ->selectRaw("SUM(CASE WHEN finding_status IN ('missing', 'unexpected', 'discrepancy') THEN 1 ELSE 0 END) as findings")
            ->selectRaw("SUM(CASE WHEN finding_status IN ('missing', 'unexpected', 'discrepancy') AND resolved_at IS NULL THEN 1 ELSE 0 END) as unresolved")
            ->groupBy('inventory_audit_id');
        $query = DB::table('inventory_audits as audits')
            ->join('branches as branches', 'branches.id', '=', 'audits.branch_id')
            ->leftJoinSub($items, 'items', static fn ($join) => $join->on('items.inventory_audit_id', '=', 'audits.id'))
            ->whereIn('audits.branch_id', $branchIds)
            ->whereBetween(DB::raw('COALESCE(audits.scheduled_at, audits.created_at)'), [$filters['from'], $filters['to']])
            ->when($filters['status'] !== 'all', fn ($builder) => $builder->where('audits.status', $filters['status']))
            ->when($filters['search'] !== '', function ($builder) use ($filters): void {
                $search = $filters['search'];
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('audits.audit_number', 'like', "%{$search}%")
                        ->orWhere('audits.title', 'like', "%{$search}%")
                        ->orWhere('branches.name', 'like', "%{$search}%")
                        ->orWhere('branches.code', 'like', "%{$search}%");
                });
            })
            ->select([
                'audits.id', 'audits.audit_number', 'audits.title', 'audits.status',
                'audits.scheduled_at', 'audits.started_at', 'audits.submitted_at',
                'audits.approved_at', 'audits.closed_at',
                'branches.code as branch_code', 'items.snapshot_items',
                'items.counted_items', 'items.findings', 'items.unresolved',
            ])
            ->orderByDesc(DB::raw('COALESCE(audits.scheduled_at, audits.created_at)'));

        /** @var Collection<int, stdClass> $records */
        $records = $query->get();
        $rows = array_values(
            $records->map(static fn (stdClass $row): array => [
                'id' => 'inventory-audit-'.(int) $row->id,
                'href' => '/inventory-audits/'.(int) $row->id,
                'values' => [
                    'audit_number' => (string) $row->audit_number,
                    'title' => (string) $row->title,
                    'branch' => (string) $row->branch_code,
                    'status' => (string) $row->status,
                    'scheduled_at' => $row->scheduled_at === null ? null : (string) $row->scheduled_at,
                    'started_at' => $row->started_at === null ? null : (string) $row->started_at,
                    'submitted_at' => $row->submitted_at === null ? null : (string) $row->submitted_at,
                    'approved_at' => $row->approved_at === null ? null : (string) $row->approved_at,
                    'closed_at' => $row->closed_at === null ? null : (string) $row->closed_at,
                    'snapshot_items' => (int) ($row->snapshot_items ?? 0),
                    'counted_items' => (int) ($row->counted_items ?? 0),
                    'findings' => (int) ($row->findings ?? 0),
                    'unresolved' => (int) ($row->unresolved ?? 0),
                ],
            ])->values()->all(),
        );

        return compact('columns', 'rows');
    }

    /** @return array<string, mixed> */
    private function column(string $key, string $label, string $type, int $exportWidth): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'export_width' => $exportWidth,
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(string $report): array
    {
        $statuses = match ($report) {
            'finance' => ['completed', 'void', 'requested', 'approved', 'paid', 'rejected', 'cancelled', 'posted'],
            'receivables', 'operational' => ['draft', 'confirmed', 'active', 'overdue', 'returned', 'completed', 'cancelled', 'void'],
            'cash' => ['open', 'closed'],
            'assets' => ['available', 'reserved', 'rented', 'maintenance', 'retired', 'lost'],
            'transfers' => ['draft', 'requested', 'approved', 'in_transit', 'received', 'closed', 'cancelled'],
            'inventory-audits' => ['draft', 'in_progress', 'submitted', 'approved', 'closed', 'cancelled'],
            default => [],
        };

        return array_map(static fn (string $status): array => [
            'value' => $status,
            'label' => str($status)->replace('_', ' ')->title()->toString(),
        ], $statuses);
    }

    private function reportLabel(string $report): string
    {
        return match ($report) {
            'finance' => 'Keuangan & Ledger',
            'receivables' => 'Piutang & Deposit',
            'cash' => 'Sesi Kas',
            'assets' => 'Aset & Maintenance',
            'transfers' => 'Transfer Antar-Cabang',
            'inventory-audits' => 'Stock Opname',
            default => 'Booking, Rental & Return',
        };
    }

    private function reportDescription(string $report): string
    {
        return match ($report) {
            'finance' => 'Payment, refund, metode pembayaran, kategori, arah dana, dan status ledger.',
            'receivables' => 'Saldo rental yang masih berjalan, umur piutang, dan deposit kontrak.',
            'cash' => 'Rekonsiliasi sesi kas per register, kasir, dan selisih penutupan.',
            'assets' => 'Kondisi aset, investasi, frekuensi maintenance, dan biaya aktual periode.',
            'transfers' => 'Pergerakan aset lintas cabang, biaya pengiriman, dan discrepancy penerimaan.',
            'inventory-audits' => 'Progres stock opname, temuan fisik, dan penyelesaian discrepancy per cabang.',
            default => 'Nilai kontrak, status rental, waktu checkout/return, denda, pembayaran, dan saldo.',
        };
    }
}
