<?php

namespace App\Domain\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class OperationalDashboardService
{
    private const ACTIVE_RENTAL_STATUSES = ['active', 'partial_return', 'correction_pending'];

    /**
     * @param  list<int>  $branchIds
     * @return array<string, mixed>
     */
    public function summarize(User $actor, array $branchIds): array
    {
        $now = CarbonImmutable::now();
        $currentFrom = $now->startOfMonth()->startOfDay();
        $currentTo = $now->endOfDay();
        $previousFrom = $currentFrom->subMonthNoOverflow()->startOfMonth();
        $previousTo = $previousFrom
            ->addDays(min($now->day - 1, $previousFrom->daysInMonth - 1))
            ->endOfDay();
        $visibility = $this->visibility($actor);

        $bookingMetrics = $visibility['bookings']
            ? $this->bookingMetrics($branchIds, $currentFrom, $currentTo, $previousFrom, $previousTo)
            : $this->emptyBookingMetrics();
        $rentalMetrics = $visibility['rentals']
            ? $this->rentalMetrics($branchIds)
            : $this->emptyRentalMetrics();
        $financeMetrics = $visibility['finance']
            ? $this->financeMetrics($branchIds, $currentFrom, $currentTo, $previousFrom, $previousTo)
            : $this->emptyFinanceMetrics();
        $assetMetrics = $visibility['assets']
            ? $this->assetMetrics($branchIds)
            : $this->emptyAssetMetrics();
        $customerMetrics = $visibility['customers']
            ? $this->customerMetrics($branchIds, $currentFrom, $currentTo)
            : ['total' => 0, 'new_this_month' => 0];

        return [
            'visibility' => $visibility,
            'overview' => [
                ...$bookingMetrics,
                ...$rentalMetrics,
                ...$financeMetrics,
                ...$assetMetrics,
                'customer_total' => $customerMetrics['total'],
                'customer_new_this_month' => $customerMetrics['new_this_month'],
            ],
            'quickActions' => $this->quickActions($actor),
            'attention' => $this->attention($actor, $branchIds),
            'trend' => $this->operationalTrend($actor, $branchIds, $now),
            'assetHealth' => $visibility['assets']
                ? $this->assetHealth($branchIds)
                : [],
            'todaySchedule' => $this->todaySchedule($actor, $branchIds),
            'branchPerformance' => $this->branchPerformance(
                $branchIds,
                $currentFrom,
                $currentTo,
                $visibility,
            ),
            'recentActivity' => $this->recentActivity($actor, $branchIds),
            'period' => [
                'from' => $currentFrom->toDateString(),
                'to' => $currentTo->toDateString(),
                'label' => $currentFrom->translatedFormat('F Y'),
                'comparison_label' => $previousFrom->translatedFormat('d M').'–'.$previousTo->translatedFormat('d M Y'),
            ],
        ];
    }

    /**
     * @return array{bookings: bool, rentals: bool, finance: bool, assets: bool, customers: bool}
     */
    private function visibility(User $actor): array
    {
        return [
            'bookings' => $actor->can('bookings.view'),
            'rentals' => $actor->can('rentals.view'),
            'finance' => $actor->can('finance.dashboard.view') || $actor->can('payments.view'),
            'assets' => $actor->can('products.view') || $actor->can('reports.view'),
            'customers' => $actor->can('customers.view'),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{bookings_month: int, bookings_change_percent: float|null, booking_conversion_percent: float}
     */
    private function bookingMetrics(
        array $branchIds,
        CarbonImmutable $currentFrom,
        CarbonImmutable $currentTo,
        CarbonImmutable $previousFrom,
        CarbonImmutable $previousTo,
    ): array {
        if ($branchIds === []) {
            return $this->emptyBookingMetrics();
        }

        $base = DB::table('bookings')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at');
        $current = (clone $base)
            ->whereBetween('booked_at', [$currentFrom, $currentTo]);
        $currentCount = (clone $current)->count();
        $previousCount = (clone $base)
            ->whereBetween('booked_at', [$previousFrom, $previousTo])
            ->count();
        $eligible = (clone $current)
            ->whereIn('status', ['confirmed', 'converted', 'completed'])
            ->count();
        $converted = (clone $current)
            ->whereIn('status', ['converted', 'completed'])
            ->count();

        return [
            'bookings_month' => $currentCount,
            'bookings_change_percent' => $this->percentageChange($currentCount, $previousCount),
            'booking_conversion_percent' => $eligible === 0
                ? 0.0
                : round(($converted / $eligible) * 100, 1),
        ];
    }

    /** @return array{bookings_month: int, bookings_change_percent: float|null, booking_conversion_percent: float} */
    private function emptyBookingMetrics(): array
    {
        return [
            'bookings_month' => 0,
            'bookings_change_percent' => 0.0,
            'booking_conversion_percent' => 0.0,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{active_rentals: int, overdue_rentals: int, due_today_rentals: int, receivable_amount: float}
     */
    private function rentalMetrics(array $branchIds): array
    {
        if ($branchIds === []) {
            return $this->emptyRentalMetrics();
        }

        $base = DB::table('rentals')
            ->whereIn('branch_id', $branchIds)
            ->whereNull('deleted_at');
        $active = (clone $base)->whereIn('status', self::ACTIVE_RENTAL_STATUSES);

        return [
            'active_rentals' => (clone $active)->count(),
            'overdue_rentals' => (clone $active)->where('due_at', '<', now())->count(),
            'due_today_rentals' => (clone $active)->whereDate('due_at', today())->count(),
            'receivable_amount' => (float) (clone $base)
                ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
                ->where('balance_due', '>', 0)
                ->sum('balance_due'),
        ];
    }

    /** @return array{active_rentals: int, overdue_rentals: int, due_today_rentals: int, receivable_amount: float} */
    private function emptyRentalMetrics(): array
    {
        return [
            'active_rentals' => 0,
            'overdue_rentals' => 0,
            'due_today_rentals' => 0,
            'receivable_amount' => 0.0,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{net_revenue_month: float, revenue_change_percent: float|null, gross_collections_month: float, refunds_month: float}
     */
    private function financeMetrics(
        array $branchIds,
        CarbonImmutable $currentFrom,
        CarbonImmutable $currentTo,
        CarbonImmutable $previousFrom,
        CarbonImmutable $previousTo,
    ): array {
        if ($branchIds === []) {
            return $this->emptyFinanceMetrics();
        }

        $current = $this->cashFlow($branchIds, $currentFrom, $currentTo);
        $previous = $this->cashFlow($branchIds, $previousFrom, $previousTo);

        return [
            'net_revenue_month' => $current['net'],
            'revenue_change_percent' => $this->percentageChange($current['net'], $previous['net']),
            'gross_collections_month' => $current['gross'],
            'refunds_month' => $current['refunds'],
        ];
    }

    /** @return array{net_revenue_month: float, revenue_change_percent: float|null, gross_collections_month: float, refunds_month: float} */
    private function emptyFinanceMetrics(): array
    {
        return [
            'net_revenue_month' => 0.0,
            'revenue_change_percent' => 0.0,
            'gross_collections_month' => 0.0,
            'refunds_month' => 0.0,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{gross: float, refunds: float, net: float}
     */
    private function cashFlow(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $payment = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as gross")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as payment_net")
            ->first();
        $refunds = (float) DB::table('refunds')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'paid')
            ->whereBetween('processed_at', [$from, $to])
            ->sum('amount');
        $gross = (float) ($payment->gross ?? 0);

        return [
            'gross' => $gross,
            'refunds' => $refunds,
            'net' => (float) ($payment->payment_net ?? 0) - $refunds,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{asset_total: int, asset_available: int, asset_rented: int, asset_maintenance: int, asset_utilization_percent: float}
     */
    private function assetMetrics(array $branchIds): array
    {
        if ($branchIds === []) {
            return $this->emptyAssetMetrics();
        }

        $base = DB::table('assets')
            ->whereIn('current_branch_id', $branchIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'retired');
        $total = (clone $base)->count();
        $rented = (clone $base)->where('status', 'rented')->count();

        return [
            'asset_total' => $total,
            'asset_available' => (clone $base)->where('status', 'available')->count(),
            'asset_rented' => $rented,
            'asset_maintenance' => (clone $base)->where('status', 'maintenance')->count(),
            'asset_utilization_percent' => $total === 0
                ? 0.0
                : round(($rented / $total) * 100, 1),
        ];
    }

    /** @return array{asset_total: int, asset_available: int, asset_rented: int, asset_maintenance: int, asset_utilization_percent: float} */
    private function emptyAssetMetrics(): array
    {
        return [
            'asset_total' => 0,
            'asset_available' => 0,
            'asset_rented' => 0,
            'asset_maintenance' => 0,
            'asset_utilization_percent' => 0.0,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{total: int, new_this_month: int}
     */
    private function customerMetrics(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        if ($branchIds === []) {
            return ['total' => 0, 'new_this_month' => 0];
        }

        $base = DB::table('customers')
            ->whereIn('registered_branch_id', $branchIds)
            ->whereNull('deleted_at');

        return [
            'total' => (clone $base)->count(),
            'new_this_month' => (clone $base)->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    /** @return list<array{key: string, title: string, description: string, href: string}> */
    private function quickActions(User $actor): array
    {
        /** @var list<array{key: string, title: string, description: string, href: string}> $actions */
        $actions = [];

        if ($actor->can('bookings.create')) {
            $actions[] = [
                'key' => 'booking',
                'title' => 'Booking Baru',
                'description' => 'Reservasi jadwal dan unit pelanggan.',
                'href' => '/bookings/create',
            ];
        }

        if ($actor->can('rentals.create')) {
            $actions[] = [
                'key' => 'direct-rental',
                'title' => 'Rental Langsung',
                'description' => 'Checkout transaksi tanpa booking.',
                'href' => '/rentals/direct/create',
            ];
        }

        if ($actor->can('rentals.return')) {
            $actions[] = [
                'key' => 'return',
                'title' => 'Proses Pengembalian',
                'description' => 'Buka rental jatuh tempo hari ini.',
                'href' => '/rentals?operational_state=due_today',
            ];
        }

        if ($actor->can('payments.view')) {
            $actions[] = [
                'key' => 'payment',
                'title' => 'Tagihan & Pembayaran',
                'description' => 'Tindak lanjuti saldo yang belum lunas.',
                'href' => $actor->can('rentals.view')
                    ? '/rentals?payment_state=outstanding'
                    : '/finance/payments',
            ];
        }

        if ($actor->can('transfers.create')) {
            $actions[] = [
                'key' => 'transfer',
                'title' => 'Transfer Aset',
                'description' => 'Ajukan perpindahan unit antar-cabang.',
                'href' => '/transfers/create',
            ];
        }

        if ($actor->can('inventory-audits.create')) {
            $actions[] = [
                'key' => 'stock-opname',
                'title' => 'Mulai Stock Opname',
                'description' => 'Buat snapshot pemeriksaan inventaris.',
                'href' => '/inventory-audits',
            ];
        }

        if ($actor->can('maintenance.manage')) {
            $actions[] = [
                'key' => 'maintenance',
                'title' => 'Lapor Maintenance',
                'description' => 'Catat unit yang perlu ditangani.',
                'href' => '/maintenance',
            ];
        }

        if ($actor->can('notifications.view')) {
            $actions[] = [
                'key' => 'notification',
                'title' => 'Buka Reminder',
                'description' => 'Lihat pekerjaan dan peringatan aktif.',
                'href' => '/notifications?state=unread',
            ];
        }

        return array_slice($actions, 0, 6);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{key: string, title: string, description: string, count: int, tone: string, href: string}>
     */
    private function attention(User $actor, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        /** @var list<array{key: string, title: string, description: string, count: int, tone: string, href: string, priority: int}> $items */
        $items = [];

        if ($actor->can('rentals.view')) {
            $active = DB::table('rentals')
                ->whereIn('branch_id', $branchIds)
                ->whereNull('deleted_at')
                ->whereIn('status', self::ACTIVE_RENTAL_STATUSES);
            $this->pushAttention($items, [
                'key' => 'rental-overdue',
                'title' => 'Rental terlambat',
                'description' => 'Sudah melewati jadwal pengembalian dan perlu segera dihubungi.',
                'count' => (clone $active)->where('due_at', '<', now())->count(),
                'tone' => 'critical',
                'href' => '/rentals?operational_state=overdue',
                'priority' => 100,
            ]);
            $this->pushAttention($items, [
                'key' => 'rental-due-today',
                'title' => 'Kembali hari ini',
                'description' => 'Siapkan pemeriksaan unit dan penyelesaian transaksi.',
                'count' => (clone $active)->whereDate('due_at', today())->count(),
                'tone' => 'warning',
                'href' => '/rentals?operational_state=due_today',
                'priority' => 80,
            ]);
        }

        if ($actor->can('bookings.view')) {
            $this->pushAttention($items, [
                'key' => 'booking-pickup',
                'title' => 'Pengambilan booking hari ini',
                'description' => 'Pastikan unit, dokumen, dan pembayaran siap sebelum pelanggan datang.',
                'count' => DB::table('bookings')
                    ->whereIn('branch_id', $branchIds)
                    ->whereNull('deleted_at')
                    ->where('status', 'confirmed')
                    ->whereDate('starts_at', today())
                    ->count(),
                'tone' => 'info',
                'href' => '/bookings?status=confirmed&period=today',
                'priority' => 70,
            ]);
        }

        if ($actor->can('payments.view')) {
            $this->pushAttention($items, [
                'key' => 'receivable',
                'title' => 'Tagihan belum lunas',
                'description' => 'Rental aktif atau selesai masih memiliki saldo pembayaran.',
                'count' => DB::table('rentals')
                    ->whereIn('branch_id', $branchIds)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['draft', 'cancelled', 'void', 'rejected'])
                    ->where('balance_due', '>', 0)
                    ->count(),
                'tone' => 'warning',
                'href' => '/rentals?payment_state=outstanding',
                'priority' => 75,
            ]);
        }

        if ($actor->can('refunds.approve')) {
            $this->pushAttention($items, [
                'key' => 'refund-approval',
                'title' => 'Refund menunggu approval',
                'description' => 'Permintaan pengembalian dana membutuhkan keputusan.',
                'count' => DB::table('refunds')
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'requested')
                    ->count(),
                'tone' => 'warning',
                'href' => '/finance/refunds?status=requested',
                'priority' => 86,
            ]);
        }

        if ($actor->can('refunds.process')) {
            $this->pushAttention($items, [
                'key' => 'refund-payment',
                'title' => 'Refund siap dibayar',
                'description' => 'Refund telah disetujui dan menunggu proses kasir.',
                'count' => DB::table('refunds')
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'approved')
                    ->count(),
                'tone' => 'warning',
                'href' => '/finance/refunds?status=approved',
                'priority' => 88,
            ]);
        }

        if ($actor->can('transfers.approve')) {
            $this->pushAttention($items, [
                'key' => 'transfer-approval',
                'title' => 'Transfer menunggu approval',
                'description' => 'Pengajuan transfer antar-cabang belum memperoleh keputusan lengkap.',
                'count' => $this->transferScope($branchIds)
                    ->where('status', 'pending_approval')
                    ->count(),
                'tone' => 'warning',
                'href' => '/transfers?status=pending_approval',
                'priority' => 84,
            ]);
        }

        if ($actor->can('transfers.dispatch')) {
            $this->pushAttention($items, [
                'key' => 'transfer-dispatch',
                'title' => 'Transfer siap diberangkatkan',
                'description' => 'Unit telah disetujui dan menunggu dokumentasi dispatch.',
                'count' => DB::table('branch_transfers')
                    ->whereIn('from_branch_id', $branchIds)
                    ->where('status', 'approved')
                    ->count(),
                'tone' => 'info',
                'href' => '/transfers?status=approved',
                'priority' => 68,
            ]);
        }

        if ($actor->can('maintenance.view')) {
            $this->pushAttention($items, [
                'key' => 'maintenance-open',
                'title' => 'Maintenance aktif',
                'description' => 'Unit masih dalam antrean laporan atau proses perbaikan.',
                'count' => DB::table('maintenance_orders')
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('status', ['reported', 'in_progress'])
                    ->count(),
                'tone' => 'neutral',
                'href' => '/maintenance?status=in_progress',
                'priority' => 55,
            ]);
        }

        if ($actor->can('inventory-audits.approve')) {
            $this->pushAttention($items, [
                'key' => 'inventory-approval',
                'title' => 'Stock opname menunggu approval',
                'description' => 'Hasil hitung fisik telah diajukan untuk ditinjau.',
                'count' => DB::table('inventory_audits')
                    ->whereIn('branch_id', $branchIds)
                    ->where('status', 'submitted')
                    ->count(),
                'tone' => 'warning',
                'href' => '/inventory-audits?status=submitted',
                'priority' => 82,
            ]);
        }

        if ($actor->can('notifications.view')) {
            $this->pushAttention($items, [
                'key' => 'critical-notification',
                'title' => 'Reminder kritis belum dibaca',
                'description' => 'Peringatan lintas modul membutuhkan perhatian pengguna ini.',
                'count' => DB::table('notification_messages')
                    ->where('user_id', $actor->id)
                    ->where(function (Builder $query) use ($branchIds): void {
                        $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                    })
                    ->where('severity', 'critical')
                    ->whereNull('read_at')
                    ->whereNull('dismissed_at')
                    ->whereNull('resolved_at')
                    ->where(function (Builder $query): void {
                        $query->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
                    })
                    ->count(),
                'tone' => 'critical',
                'href' => '/notifications?severity=critical&state=unread',
                'priority' => 95,
            ]);
        }

        usort($items, static fn (array $first, array $second): int =>
            [$second['priority'], $second['count']] <=> [$first['priority'], $first['count']]);

        return array_values(array_map(static function (array $item): array {
            unset($item['priority']);

            return $item;
        }, array_slice($items, 0, 8)));
    }

    /**
     * @param  list<array{key: string, title: string, description: string, count: int, tone: string, href: string, priority: int}>  $items
     * @param  array{key: string, title: string, description: string, count: int, tone: string, href: string, priority: int}  $item
     */
    private function pushAttention(array &$items, array $item): void
    {
        if ($item['count'] > 0) {
            $items[] = $item;
        }
    }

    /** @param list<int> $branchIds */
    private function transferScope(array $branchIds): Builder
    {
        return DB::table('branch_transfers')
            ->where(function (Builder $query) use ($branchIds): void {
                $query
                    ->whereIn('from_branch_id', $branchIds)
                    ->orWhereIn('to_branch_id', $branchIds);
            });
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{date: string, label: string, bookings: int, rentals: int}>
     */
    private function operationalTrend(User $actor, array $branchIds, CarbonImmutable $now): array
    {
        /** @var array<string, array{date: string, label: string, bookings: int, rentals: int}> $buckets */
        $buckets = [];
        $from = $now->subDays(13)->startOfDay();

        for ($day = $from; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $buckets[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'label' => $day->translatedFormat('d M'),
                'bookings' => 0,
                'rentals' => 0,
            ];
        }

        if ($branchIds === []) {
            return array_values($buckets);
        }

        if ($actor->can('bookings.view')) {
            /** @var Collection<int, stdClass> $rows */
            $rows = DB::table('bookings')
                ->whereIn('branch_id', $branchIds)
                ->whereNull('deleted_at')
                ->whereBetween('booked_at', [$from, $now->endOfDay()])
                ->selectRaw('DATE(booked_at) as occurred_on, COUNT(*) as total')
                ->groupBy('occurred_on')
                ->get();

            foreach ($rows as $row) {
                $key = (string) $row->occurred_on;
                if (isset($buckets[$key])) {
                    $buckets[$key]['bookings'] = (int) $row->total;
                }
            }
        }

        if ($actor->can('rentals.view')) {
            /** @var Collection<int, stdClass> $rows */
            $rows = DB::table('rentals')
                ->whereIn('branch_id', $branchIds)
                ->whereNull('deleted_at')
                ->whereNotNull('checked_out_at')
                ->whereBetween('checked_out_at', [$from, $now->endOfDay()])
                ->selectRaw('DATE(checked_out_at) as occurred_on, COUNT(*) as total')
                ->groupBy('occurred_on')
                ->get();

            foreach ($rows as $row) {
                $key = (string) $row->occurred_on;
                if (isset($buckets[$key])) {
                    $buckets[$key]['rentals'] = (int) $row->total;
                }
            }
        }

        return array_values($buckets);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{key: string, label: string, count: int}>
     */
    private function assetHealth(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        $counts = DB::table('assets')
            ->whereIn('current_branch_id', $branchIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        /** @var list<array{0: string, 1: string}> $definitions */
        $definitions = [
            ['available', 'Tersedia'],
            ['reserved', 'Dipesan'],
            ['rented', 'Disewa'],
            ['maintenance', 'Maintenance'],
            ['in_transit', 'Dalam transfer'],
            ['lost', 'Hilang'],
        ];

        return array_map(static fn ($definition): array => [
            'key' => $definition[0],
            'label' => $definition[1],
            'count' => (int) ($counts->get($definition[0], 0)),
        ], $definitions);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, kind: string, number: string, customer: string, branch: string, scheduled_at: string, status: string, href: string}>
     */
    private function todaySchedule(User $actor, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        /** @var list<array{id: int, kind: string, number: string, customer: string, branch: string, scheduled_at: string, status: string, href: string}> $items */
        $items = [];

        if ($actor->can('bookings.view')) {
            /** @var Collection<int, stdClass> $bookings */
            $bookings = DB::table('bookings')
                ->join('customers', 'customers.id', '=', 'bookings.customer_id')
                ->join('branches', 'branches.id', '=', 'bookings.branch_id')
                ->whereIn('bookings.branch_id', $branchIds)
                ->whereNull('bookings.deleted_at')
                ->whereIn('bookings.status', ['draft', 'confirmed'])
                ->whereDate('bookings.starts_at', today())
                ->orderBy('bookings.starts_at')
                ->limit(8)
                ->get([
                    'bookings.id',
                    'bookings.booking_number',
                    'bookings.starts_at',
                    'bookings.status',
                    'customers.name as customer_name',
                    'branches.code as branch_code',
                ]);

            foreach ($bookings as $booking) {
                $items[] = [
                    'id' => (int) $booking->id,
                    'kind' => 'pickup',
                    'number' => (string) $booking->booking_number,
                    'customer' => (string) $booking->customer_name,
                    'branch' => (string) $booking->branch_code,
                    'scheduled_at' => CarbonImmutable::parse((string) $booking->starts_at)->toIso8601String(),
                    'status' => (string) $booking->status,
                    'href' => '/bookings/'.(int) $booking->id,
                ];
            }
        }

        if ($actor->can('rentals.view')) {
            /** @var Collection<int, stdClass> $rentals */
            $rentals = DB::table('rentals')
                ->join('customers', 'customers.id', '=', 'rentals.customer_id')
                ->join('branches', 'branches.id', '=', 'rentals.branch_id')
                ->whereIn('rentals.branch_id', $branchIds)
                ->whereNull('rentals.deleted_at')
                ->whereIn('rentals.status', self::ACTIVE_RENTAL_STATUSES)
                ->whereDate('rentals.due_at', today())
                ->orderBy('rentals.due_at')
                ->limit(8)
                ->get([
                    'rentals.id',
                    'rentals.rental_number',
                    'rentals.due_at',
                    'rentals.status',
                    'customers.name as customer_name',
                    'branches.code as branch_code',
                ]);

            foreach ($rentals as $rental) {
                $items[] = [
                    'id' => (int) $rental->id,
                    'kind' => 'return',
                    'number' => (string) $rental->rental_number,
                    'customer' => (string) $rental->customer_name,
                    'branch' => (string) $rental->branch_code,
                    'scheduled_at' => CarbonImmutable::parse((string) $rental->due_at)->toIso8601String(),
                    'status' => (string) $rental->status,
                    'href' => '/rentals/'.(int) $rental->id,
                ];
            }
        }

        usort($items, static fn (array $first, array $second): int =>
            $first['scheduled_at'] <=> $second['scheduled_at']);

        return array_slice($items, 0, 8);
    }

    /**
     * @param  list<int>  $branchIds
     * @param  array{bookings: bool, rentals: bool, finance: bool, assets: bool, customers: bool}  $visibility
     * @return list<array{id: int, code: string, name: string, bookings: int, active_rentals: int, overdue_rentals: int, revenue: float, utilization_percent: float}>
     */
    private function branchPerformance(
        array $branchIds,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $visibility,
    ): array {
        if ($branchIds === []) {
            return [];
        }

        /** @var Collection<int, stdClass> $branches */
        $branches = DB::table('branches')
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $bookingCounts = $visibility['bookings']
            ? DB::table('bookings')
                ->whereIn('branch_id', $branchIds)
                ->whereNull('deleted_at')
                ->whereBetween('booked_at', [$from, $to])
                ->selectRaw('branch_id, COUNT(*) as total')
                ->groupBy('branch_id')
                ->pluck('total', 'branch_id')
            : collect();

        /** @var Collection<int, stdClass> $rentalRows */
        $rentalRows = $visibility['rentals']
            ? DB::table('rentals')
                ->whereIn('branch_id', $branchIds)
                ->whereNull('deleted_at')
                ->whereIn('status', self::ACTIVE_RENTAL_STATUSES)
                ->selectRaw('branch_id, COUNT(*) as active_total')
                ->selectRaw('SUM(CASE WHEN due_at < ? THEN 1 ELSE 0 END) as overdue_total', [now()])
                ->groupBy('branch_id')
                ->get()
            : collect();
        $rentalsByBranch = $rentalRows->keyBy('branch_id');

        /** @var Collection<int, stdClass> $assetRows */
        $assetRows = $visibility['assets']
            ? DB::table('assets')
                ->whereIn('current_branch_id', $branchIds)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'retired')
                ->selectRaw('current_branch_id as branch_id, COUNT(*) as asset_total')
                ->selectRaw("SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented_total")
                ->groupBy('current_branch_id')
                ->get()
            : collect();
        $assetsByBranch = $assetRows->keyBy('branch_id');

        /** @var Collection<int, stdClass> $paymentRows */
        $paymentRows = $visibility['finance']
            ? DB::table('payments')
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$from, $to])
                ->selectRaw('branch_id')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as payment_net")
                ->groupBy('branch_id')
                ->get()
            : collect();
        $paymentsByBranch = $paymentRows->keyBy('branch_id');
        $refundsByBranch = $visibility['finance']
            ? DB::table('refunds')
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'paid')
                ->whereBetween('processed_at', [$from, $to])
                ->selectRaw('branch_id, COALESCE(SUM(amount), 0) as total')
                ->groupBy('branch_id')
                ->pluck('total', 'branch_id')
            : collect();

        return array_values($branches->map(function (stdClass $branch) use (
            $bookingCounts,
            $rentalsByBranch,
            $assetsByBranch,
            $paymentsByBranch,
            $refundsByBranch,
        ): array {
            $branchId = (int) $branch->id;
            $rental = $rentalsByBranch->get($branchId);
            $asset = $assetsByBranch->get($branchId);
            $payment = $paymentsByBranch->get($branchId);
            $assetTotal = (int) ($asset->asset_total ?? 0);
            $assetRented = (int) ($asset->rented_total ?? 0);

            return [
                'id' => $branchId,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'bookings' => (int) $bookingCounts->get($branchId, 0),
                'active_rentals' => (int) ($rental->active_total ?? 0),
                'overdue_rentals' => (int) ($rental->overdue_total ?? 0),
                'revenue' => (float) ($payment->payment_net ?? 0)
                    - (float) $refundsByBranch->get($branchId, 0),
                'utilization_percent' => $assetTotal === 0
                    ? 0.0
                    : round(($assetRented / $assetTotal) * 100, 1),
            ];
        })->values()->all());
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, kind: string, number: string, customer: string|null, branch: string, amount: float, occurred_at: string, status: string, href: string}>
     */
    private function recentActivity(User $actor, array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        /** @var list<array{id: int, kind: string, number: string, customer: string|null, branch: string, amount: float, occurred_at: string, status: string, href: string}> $items */
        $items = [];

        if ($actor->can('bookings.view')) {
            /** @var Collection<int, stdClass> $bookings */
            $bookings = DB::table('bookings')
                ->join('customers', 'customers.id', '=', 'bookings.customer_id')
                ->join('branches', 'branches.id', '=', 'bookings.branch_id')
                ->whereIn('bookings.branch_id', $branchIds)
                ->whereNull('bookings.deleted_at')
                ->latest('bookings.booked_at')
                ->limit(5)
                ->get([
                    'bookings.id',
                    'bookings.booking_number',
                    'bookings.status',
                    'bookings.total_amount',
                    'bookings.booked_at',
                    'customers.name as customer_name',
                    'branches.code as branch_code',
                ]);

            foreach ($bookings as $booking) {
                $items[] = [
                    'id' => (int) $booking->id,
                    'kind' => 'booking',
                    'number' => (string) $booking->booking_number,
                    'customer' => (string) $booking->customer_name,
                    'branch' => (string) $booking->branch_code,
                    'amount' => (float) $booking->total_amount,
                    'occurred_at' => CarbonImmutable::parse((string) $booking->booked_at)->toIso8601String(),
                    'status' => (string) $booking->status,
                    'href' => '/bookings/'.(int) $booking->id,
                ];
            }
        }

        if ($actor->can('rentals.view')) {
            /** @var Collection<int, stdClass> $rentals */
            $rentals = DB::table('rentals')
                ->join('customers', 'customers.id', '=', 'rentals.customer_id')
                ->join('branches', 'branches.id', '=', 'rentals.branch_id')
                ->whereIn('rentals.branch_id', $branchIds)
                ->whereNull('rentals.deleted_at')
                ->whereNotNull('rentals.checked_out_at')
                ->latest('rentals.checked_out_at')
                ->limit(5)
                ->get([
                    'rentals.id',
                    'rentals.rental_number',
                    'rentals.status',
                    'rentals.total_amount',
                    'rentals.checked_out_at',
                    'customers.name as customer_name',
                    'branches.code as branch_code',
                ]);

            foreach ($rentals as $rental) {
                $items[] = [
                    'id' => (int) $rental->id,
                    'kind' => 'rental',
                    'number' => (string) $rental->rental_number,
                    'customer' => (string) $rental->customer_name,
                    'branch' => (string) $rental->branch_code,
                    'amount' => (float) $rental->total_amount,
                    'occurred_at' => CarbonImmutable::parse((string) $rental->checked_out_at)->toIso8601String(),
                    'status' => (string) $rental->status,
                    'href' => '/rentals/'.(int) $rental->id,
                ];
            }
        }

        if ($actor->can('payments.view')) {
            /** @var Collection<int, stdClass> $payments */
            $payments = DB::table('payments')
                ->leftJoin('customers', 'customers.id', '=', 'payments.customer_id')
                ->join('branches', 'branches.id', '=', 'payments.branch_id')
                ->whereIn('payments.branch_id', $branchIds)
                ->latest('payments.paid_at')
                ->limit(5)
                ->get([
                    'payments.id',
                    'payments.payment_number',
                    'payments.status',
                    'payments.amount',
                    'payments.paid_at',
                    'customers.name as customer_name',
                    'branches.code as branch_code',
                ]);

            foreach ($payments as $payment) {
                $items[] = [
                    'id' => (int) $payment->id,
                    'kind' => 'payment',
                    'number' => (string) $payment->payment_number,
                    'customer' => $payment->customer_name === null ? null : (string) $payment->customer_name,
                    'branch' => (string) $payment->branch_code,
                    'amount' => (float) $payment->amount,
                    'occurred_at' => CarbonImmutable::parse((string) $payment->paid_at)->toIso8601String(),
                    'status' => (string) $payment->status,
                    'href' => '/finance/payments/'.(int) $payment->id,
                ];
            }
        }

        usort($items, static fn (array $first, array $second): int =>
            $second['occurred_at'] <=> $first['occurred_at']);

        return array_slice($items, 0, 8);
    }

    private function percentageChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return round((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 1);
    }
}
