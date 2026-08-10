<?php

namespace App\Domain\Notifications;

use App\Models\Booking;
use App\Models\BranchTransfer;
use App\Models\CashSession;
use App\Models\InventoryAudit;
use App\Models\MaintenanceOrder;
use App\Models\NotificationRule;
use App\Models\Refund;
use App\Models\Rental;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * @phpstan-type ReminderAlert array{
 *     branch_id: int|null,
 *     source_type: string,
 *     source_id: int,
 *     title: string,
 *     body: string,
 *     action_url: string|null,
 *     due_at: mixed,
 *     metadata?: array<string, mixed>
 * }
 */
class NotificationReminderGenerator
{
    public function __construct(
        private readonly NotificationManager $notifications,
    ) {}

    /**
     * @return array{rules: int, sources: int, created: int, repeated: int, resolved: int}
     */
    public function generateForCompany(int $companyId): array
    {
        $this->ensureRules($companyId);
        $result = ['rules' => 0, 'sources' => 0, 'created' => 0, 'repeated' => 0, 'resolved' => 0];

        /** @var Collection<int, NotificationRule> $rules */
        $rules = NotificationRule::query()
            ->where('company_id', $companyId)
            ->where('is_enabled', true)
            ->orderBy('code')
            ->get();

        foreach ($rules as $rule) {
            $alerts = $this->scan($rule);
            $activeSourceIds = [];
            $result['rules']++;
            $result['sources'] += count($alerts);

            foreach ($alerts as $alert) {
                $activeSourceIds[] = $alert['source_id'];
                $published = $this->notifications->publish($rule, $alert);
                $result['created'] += $published['created'];
                $result['repeated'] += $published['repeated'];
            }

            $result['resolved'] += $this->notifications->resolveMissing(
                $rule,
                array_values(array_unique($activeSourceIds)),
            );
        }

        return $result;
    }

    public function ensureRules(int $companyId): void
    {
        foreach (NotificationRuleCatalog::definitions() as $definition) {
            NotificationRule::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $definition['code']],
                [...$definition, 'is_enabled' => true],
            );
        }
    }

    /** @return list<ReminderAlert> */
    private function scan(NotificationRule $rule): array
    {
        return match ($rule->code) {
            'booking.starting_soon' => $this->bookingsStartingSoon($rule),
            'rental.due_soon' => $this->rentalsDueSoon($rule),
            'rental.overdue' => $this->overdueRentals($rule),
            'rental.balance_due' => $this->rentalBalances($rule),
            'refund.awaiting_approval' => $this->refundsAwaiting($rule, 'requested'),
            'refund.awaiting_payment' => $this->refundsAwaiting($rule, 'approved'),
            'cash.open_too_long' => $this->openCashSessions($rule),
            'transfer.awaiting_approval' => $this->transfersAwaitingApproval($rule),
            'transfer.dispatch_due' => $this->transfersDueForDispatch($rule),
            'transfer.arrival_overdue' => $this->transfersArrivalOverdue($rule),
            'maintenance.stale' => $this->staleMaintenance($rule),
            'inventory.scheduled' => $this->scheduledInventoryAudits($rule),
            'inventory.awaiting_approval' => $this->inventoryAuditsAwaitingApproval($rule),
            'inventory.unresolved_findings' => $this->inventoryAuditFindings($rule),
            default => [],
        };
    }

    /** @return list<ReminderAlert> */
    private function bookingsStartingSoon(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('bookings')
            ->join('branches', 'branches.id', '=', 'bookings.branch_id')
            ->join('customers', 'customers.id', '=', 'bookings.customer_id')
            ->where('branches.company_id', $rule->company_id)
            ->where('bookings.status', 'confirmed')
            ->whereNull('bookings.deleted_at')
            ->whereBetween('bookings.starts_at', [now(), now()->addMinutes($rule->lead_minutes)])
            ->get([
                'bookings.id', 'bookings.branch_id', 'bookings.booking_number',
                'bookings.starts_at', 'customers.name as customer_name', 'branches.code as branch_code',
            ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => Booking::class,
            'source_id' => (int) $row->id,
            'title' => 'Booking '.$row->booking_number.' segera dimulai',
            'body' => sprintf(
                'Booking %s untuk %s di cabang %s dijadwalkan mulai %s.',
                (string) $row->booking_number,
                (string) $row->customer_name,
                (string) $row->branch_code,
                $this->dateTime((string) $row->starts_at),
            ),
            'action_url' => '/bookings/'.(int) $row->id,
            'due_at' => $row->starts_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function rentalsDueSoon(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = $this->rentalBaseQuery($rule)
            ->whereIn('rentals.status', ['active', 'partial_return', 'correction_pending'])
            ->whereBetween('rentals.due_at', [now(), now()->addMinutes($rule->lead_minutes)])
            ->get();

        return $this->rentalAlerts($rows, false);
    }

    /** @return list<ReminderAlert> */
    private function overdueRentals(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = $this->rentalBaseQuery($rule)
            ->whereIn('rentals.status', ['active', 'partial_return', 'correction_pending'])
            ->where('rentals.due_at', '<', now())
            ->get();

        return $this->rentalAlerts($rows, true);
    }

    /** @return list<ReminderAlert> */
    private function rentalBalances(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = $this->rentalBaseQuery($rule)
            ->whereIn('rentals.status', ['active', 'partial_return', 'correction_pending', 'returned'])
            ->where('rentals.balance_due', '>', 0)
            ->where('rentals.due_at', '<=', now()->addMinutes($rule->lead_minutes))
            ->get();

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => Rental::class,
            'source_id' => (int) $row->id,
            'title' => 'Saldo '.$row->rental_number.' belum lunas',
            'body' => sprintf(
                'Rental %s atas nama %s masih memiliki saldo %s.',
                (string) $row->rental_number,
                (string) $row->customer_name,
                $this->money((float) $row->balance_due),
            ),
            'action_url' => '/rentals/'.(int) $row->id,
            'due_at' => $row->due_at,
            'metadata' => ['balance_due' => (float) $row->balance_due],
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function refundsAwaiting(NotificationRule $rule, string $status): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('refunds')
            ->join('branches', 'branches.id', '=', 'refunds.branch_id')
            ->where('branches.company_id', $rule->company_id)
            ->where('refunds.status', $status)
            ->get([
                'refunds.id', 'refunds.branch_id', 'refunds.refund_number',
                'refunds.amount', 'refunds.created_at', 'branches.code as branch_code',
            ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => Refund::class,
            'source_id' => (int) $row->id,
            'title' => $status === 'requested'
                ? 'Refund '.$row->refund_number.' menunggu persetujuan'
                : 'Refund '.$row->refund_number.' siap diproses',
            'body' => sprintf(
                'Refund %s senilai %s untuk cabang %s berstatus %s.',
                (string) $row->refund_number,
                $this->money((float) $row->amount),
                (string) $row->branch_code,
                $status === 'requested' ? 'menunggu approval' : 'telah disetujui',
            ),
            'action_url' => '/finance/refunds/'.(int) $row->id,
            'due_at' => $row->created_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function openCashSessions(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->join('branches', 'branches.id', '=', 'cash_registers.branch_id')
            ->where('branches.company_id', $rule->company_id)
            ->where('cash_sessions.status', 'open')
            ->where('cash_sessions.opened_at', '<=', now()->subMinutes($rule->lead_minutes))
            ->get([
                'cash_sessions.id', 'cash_sessions.opened_at', 'cash_registers.branch_id',
                'cash_registers.code', 'cash_registers.name', 'branches.code as branch_code',
            ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => CashSession::class,
            'source_id' => (int) $row->id,
            'title' => 'Sesi kas '.$row->code.' masih terbuka',
            'body' => sprintf(
                'Sesi %s · %s di cabang %s telah terbuka sejak %s.',
                (string) $row->code,
                (string) $row->name,
                (string) $row->branch_code,
                $this->dateTime((string) $row->opened_at),
            ),
            'action_url' => '/finance/master-data',
            'due_at' => $row->opened_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function transfersAwaitingApproval(NotificationRule $rule): array
    {
        return $this->transferAlerts(
            $rule,
            ['pending_approval'],
            null,
            'Pengajuan transfer menunggu approval',
            false,
        );
    }

    /** @return list<ReminderAlert> */
    private function transfersDueForDispatch(NotificationRule $rule): array
    {
        return $this->transferAlerts(
            $rule,
            ['approved'],
            ['planned_dispatch_at', '<=', now()->addMinutes($rule->lead_minutes)],
            'Transfer siap diberangkatkan',
            false,
        );
    }

    /** @return list<ReminderAlert> */
    private function transfersArrivalOverdue(NotificationRule $rule): array
    {
        return $this->transferAlerts(
            $rule,
            ['dispatched', 'receiving', 'discrepancy'],
            ['expected_arrival_at', '<', now()],
            'Transfer melewati estimasi tiba',
            true,
        );
    }

    /** @return list<ReminderAlert> */
    private function staleMaintenance(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('maintenance_orders')
            ->join('branches', 'branches.id', '=', 'maintenance_orders.branch_id')
            ->join('assets', 'assets.id', '=', 'maintenance_orders.asset_id')
            ->where('branches.company_id', $rule->company_id)
            ->whereIn('maintenance_orders.status', ['reported', 'in_progress'])
            ->whereRaw(
                'COALESCE(maintenance_orders.started_at, maintenance_orders.reported_at) <= ?',
                [now()->subMinutes($rule->lead_minutes)],
            )
            ->get([
                'maintenance_orders.id', 'maintenance_orders.branch_id',
                'maintenance_orders.maintenance_number', 'maintenance_orders.status',
                'maintenance_orders.reported_at', 'maintenance_orders.started_at',
                'assets.asset_code', 'branches.code as branch_code',
            ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => MaintenanceOrder::class,
            'source_id' => (int) $row->id,
            'title' => 'Maintenance '.$row->maintenance_number.' belum selesai',
            'body' => sprintf(
                'Aset %s di cabang %s masih berstatus %s sejak %s.',
                (string) $row->asset_code,
                (string) $row->branch_code,
                (string) $row->status,
                $this->dateTime((string) ($row->started_at ?? $row->reported_at)),
            ),
            'action_url' => '/maintenance/'.(int) $row->id,
            'due_at' => $row->started_at ?? $row->reported_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function scheduledInventoryAudits(NotificationRule $rule): array
    {
        /** @var Collection<int, stdClass> $rows */
        $rows = DB::table('inventory_audits')
            ->join('branches', 'branches.id', '=', 'inventory_audits.branch_id')
            ->where('inventory_audits.company_id', $rule->company_id)
            ->where('inventory_audits.status', 'draft')
            ->whereNotNull('inventory_audits.scheduled_at')
            ->where('inventory_audits.scheduled_at', '<=', now()->addMinutes($rule->lead_minutes))
            ->get([
                'inventory_audits.id', 'inventory_audits.branch_id',
                'inventory_audits.audit_number', 'inventory_audits.title',
                'inventory_audits.scheduled_at', 'branches.code as branch_code',
            ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => InventoryAudit::class,
            'source_id' => (int) $row->id,
            'title' => 'Stock opname '.$row->audit_number.' segera dimulai',
            'body' => sprintf(
                '%s di cabang %s dijadwalkan pada %s.',
                (string) $row->title,
                (string) $row->branch_code,
                $this->dateTime((string) $row->scheduled_at),
            ),
            'action_url' => '/inventory-audits/'.(int) $row->id,
            'due_at' => $row->scheduled_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function inventoryAuditsAwaitingApproval(NotificationRule $rule): array
    {
        return $this->inventoryStatusAlerts($rule, 'submitted', false);
    }

    /** @return list<ReminderAlert> */
    private function inventoryAuditFindings(NotificationRule $rule): array
    {
        return $this->inventoryStatusAlerts($rule, 'approved', true);
    }

    private function rentalBaseQuery(NotificationRule $rule): Builder
    {
        return DB::table('rentals')
            ->join('branches', 'branches.id', '=', 'rentals.branch_id')
            ->join('customers', 'customers.id', '=', 'rentals.customer_id')
            ->where('branches.company_id', $rule->company_id)
            ->whereNull('rentals.deleted_at')
            ->select([
                'rentals.id', 'rentals.branch_id', 'rentals.rental_number', 'rentals.status',
                'rentals.due_at', 'rentals.balance_due', 'customers.name as customer_name',
                'branches.code as branch_code',
            ]);
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<ReminderAlert>
     */
    private function rentalAlerts(Collection $rows, bool $overdue): array
    {
        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => Rental::class,
            'source_id' => (int) $row->id,
            'title' => $overdue
                ? 'Rental '.$row->rental_number.' terlambat'
                : 'Rental '.$row->rental_number.' mendekati jatuh tempo',
            'body' => sprintf(
                'Rental %s atas nama %s %s %s%s.',
                (string) $row->rental_number,
                (string) $row->customer_name,
                $overdue ? 'telah melewati jatuh tempo' : 'jatuh tempo pada',
                $this->dateTime((string) $row->due_at),
                (float) $row->balance_due > 0 ? ' dengan saldo '.$this->money((float) $row->balance_due) : '',
            ),
            'action_url' => '/rentals/'.(int) $row->id,
            'due_at' => $row->due_at,
            'metadata' => ['balance_due' => (float) $row->balance_due],
        ])->all());
    }

    /**
     * @param  list<string>  $statuses
     * @param  array{0: string, 1: string, 2: mixed}|null  $dateConstraint
     * @return list<ReminderAlert>
     */
    private function transferAlerts(
        NotificationRule $rule,
        array $statuses,
        ?array $dateConstraint,
        string $title,
        bool $destination,
    ): array {
        $query = DB::table('branch_transfers')
            ->join('branches as origin', 'origin.id', '=', 'branch_transfers.from_branch_id')
            ->join('branches as destination', 'destination.id', '=', 'branch_transfers.to_branch_id')
            ->where('branch_transfers.company_id', $rule->company_id)
            ->whereIn('branch_transfers.status', $statuses);

        if ($dateConstraint !== null) {
            $query
                ->whereNotNull('branch_transfers.'.$dateConstraint[0])
                ->where('branch_transfers.'.$dateConstraint[0], $dateConstraint[1], $dateConstraint[2]);
        }

        /** @var Collection<int, stdClass> $rows */
        $rows = $query->get([
            'branch_transfers.id', 'branch_transfers.from_branch_id',
            'branch_transfers.to_branch_id', 'branch_transfers.transfer_number',
            'branch_transfers.status', 'branch_transfers.requested_at',
            'branch_transfers.planned_dispatch_at', 'branch_transfers.expected_arrival_at',
            'origin.code as origin_code', 'destination.code as destination_code',
        ]);

        return array_values($rows->map(fn (stdClass $row): array => [
            'branch_id' => (int) ($destination ? $row->to_branch_id : $row->from_branch_id),
            'source_type' => BranchTransfer::class,
            'source_id' => (int) $row->id,
            'title' => $title.' · '.$row->transfer_number,
            'body' => sprintf(
                'Transfer %s dari %s ke %s berstatus %s.',
                (string) $row->transfer_number,
                (string) $row->origin_code,
                (string) $row->destination_code,
                (string) $row->status,
            ),
            'action_url' => '/transfers/'.(int) $row->id,
            'due_at' => $row->expected_arrival_at ?? $row->planned_dispatch_at ?? $row->requested_at,
        ])->all());
    }

    /** @return list<ReminderAlert> */
    private function inventoryStatusAlerts(
        NotificationRule $rule,
        string $status,
        bool $onlyUnresolved,
    ): array {
        $query = DB::table('inventory_audits')
            ->join('branches', 'branches.id', '=', 'inventory_audits.branch_id')
            ->where('inventory_audits.company_id', $rule->company_id)
            ->where('inventory_audits.status', $status)
            ->leftJoinSub(
                DB::table('inventory_audit_items')
                    ->select('inventory_audit_id')
                    ->selectRaw('COUNT(*) as unresolved_count')
                    ->whereIn('finding_status', ['discrepancy', 'missing', 'unexpected'])
                    ->whereNull('resolved_at')
                    ->groupBy('inventory_audit_id'),
                'findings',
                'findings.inventory_audit_id',
                '=',
                'inventory_audits.id',
            );

        if ($onlyUnresolved) {
            $query->whereRaw('COALESCE(findings.unresolved_count, 0) > 0');
        }

        /** @var Collection<int, stdClass> $rows */
        $rows = $query->get([
            'inventory_audits.id', 'inventory_audits.branch_id',
            'inventory_audits.audit_number', 'inventory_audits.title',
            'inventory_audits.submitted_at', 'inventory_audits.approved_at',
            'branches.code as branch_code',
            DB::raw('COALESCE(findings.unresolved_count, 0) as unresolved_count'),
        ]);

        return array_values($rows->map(static fn (stdClass $row): array => [
            'branch_id' => (int) $row->branch_id,
            'source_type' => InventoryAudit::class,
            'source_id' => (int) $row->id,
            'title' => $onlyUnresolved
                ? 'Temuan '.$row->audit_number.' belum ditindaklanjuti'
                : 'Stock opname '.$row->audit_number.' menunggu approval',
            'body' => $onlyUnresolved
                ? sprintf(
                    '%s di cabang %s memiliki %d temuan yang belum diselesaikan.',
                    (string) $row->title,
                    (string) $row->branch_code,
                    (int) $row->unresolved_count,
                )
                : sprintf(
                    '%s di cabang %s telah diajukan dan menunggu pemeriksaan kedua.',
                    (string) $row->title,
                    (string) $row->branch_code,
                ),
            'action_url' => '/inventory-audits/'.(int) $row->id,
            'due_at' => $row->approved_at ?? $row->submitted_at,
            'metadata' => ['unresolved_count' => (int) $row->unresolved_count],
        ])->all());
    }

    private function dateTime(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('d M Y H:i', $timestamp);
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
