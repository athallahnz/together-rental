<?php

namespace App\Domain\Notifications;

final class NotificationRuleCatalog
{
    /**
     * @return list<array{
     *     code: string,
     *     category: string,
     *     name: string,
     *     description: string,
     *     severity: string,
     *     recipient_permission: string,
     *     lead_minutes: int,
     *     repeat_minutes: int
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'booking.starting_soon',
                'category' => 'booking',
                'name' => 'Booking segera dimulai',
                'description' => 'Mengingatkan tim operasional sebelum jadwal pengambilan booking.',
                'severity' => 'info',
                'recipient_permission' => 'bookings.view',
                'lead_minutes' => 1440,
                'repeat_minutes' => 720,
            ],
            [
                'code' => 'rental.due_soon',
                'category' => 'rental',
                'name' => 'Rental mendekati jatuh tempo',
                'description' => 'Mengingatkan tim sebelum batas pengembalian rental.',
                'severity' => 'warning',
                'recipient_permission' => 'rentals.view',
                'lead_minutes' => 1440,
                'repeat_minutes' => 360,
            ],
            [
                'code' => 'rental.overdue',
                'category' => 'rental',
                'name' => 'Rental terlambat',
                'description' => 'Memberi peringatan untuk rental aktif yang melewati jatuh tempo.',
                'severity' => 'critical',
                'recipient_permission' => 'rentals.view',
                'lead_minutes' => 0,
                'repeat_minutes' => 1440,
            ],
            [
                'code' => 'rental.balance_due',
                'category' => 'finance',
                'name' => 'Saldo rental belum lunas',
                'description' => 'Mengingatkan saldo rental yang masih harus dibayar.',
                'severity' => 'warning',
                'recipient_permission' => 'payments.view',
                'lead_minutes' => 1440,
                'repeat_minutes' => 1440,
            ],
            [
                'code' => 'refund.awaiting_approval',
                'category' => 'finance',
                'name' => 'Refund menunggu persetujuan',
                'description' => 'Memberi tahu approver saat ada permintaan refund baru.',
                'severity' => 'warning',
                'recipient_permission' => 'refunds.approve',
                'lead_minutes' => 0,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'refund.awaiting_payment',
                'category' => 'finance',
                'name' => 'Refund siap diproses',
                'description' => 'Memberi tahu kasir saat refund telah disetujui.',
                'severity' => 'warning',
                'recipient_permission' => 'refunds.process',
                'lead_minutes' => 0,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'cash.open_too_long',
                'category' => 'finance',
                'name' => 'Sesi kas terbuka terlalu lama',
                'description' => 'Mengingatkan sesi kas yang belum ditutup setelah batas operasional.',
                'severity' => 'warning',
                'recipient_permission' => 'cash.manage',
                'lead_minutes' => 720,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'transfer.awaiting_approval',
                'category' => 'transfer',
                'name' => 'Transfer menunggu approval',
                'description' => 'Mengingatkan approver atas pengajuan transfer antar-cabang.',
                'severity' => 'warning',
                'recipient_permission' => 'transfers.approve',
                'lead_minutes' => 0,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'transfer.dispatch_due',
                'category' => 'transfer',
                'name' => 'Transfer siap diberangkatkan',
                'description' => 'Mengingatkan jadwal keberangkatan transfer yang telah disetujui.',
                'severity' => 'warning',
                'recipient_permission' => 'transfers.dispatch',
                'lead_minutes' => 120,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'transfer.arrival_overdue',
                'category' => 'transfer',
                'name' => 'Transfer melewati estimasi tiba',
                'description' => 'Memberi peringatan transfer yang belum diterima setelah estimasi tiba.',
                'severity' => 'critical',
                'recipient_permission' => 'transfers.receive',
                'lead_minutes' => 0,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'maintenance.stale',
                'category' => 'maintenance',
                'name' => 'Maintenance belum ditindaklanjuti',
                'description' => 'Mengingatkan maintenance aktif yang tidak selesai dalam batas waktu.',
                'severity' => 'warning',
                'recipient_permission' => 'maintenance.manage',
                'lead_minutes' => 1440,
                'repeat_minutes' => 1440,
            ],
            [
                'code' => 'inventory.scheduled',
                'category' => 'inventory',
                'name' => 'Jadwal stock opname',
                'description' => 'Mengingatkan stock opname draft yang segera dijalankan.',
                'severity' => 'info',
                'recipient_permission' => 'inventory-audits.count',
                'lead_minutes' => 1440,
                'repeat_minutes' => 720,
            ],
            [
                'code' => 'inventory.awaiting_approval',
                'category' => 'inventory',
                'name' => 'Stock opname menunggu approval',
                'description' => 'Mengingatkan hasil stock opname yang telah diajukan.',
                'severity' => 'warning',
                'recipient_permission' => 'inventory-audits.approve',
                'lead_minutes' => 0,
                'repeat_minutes' => 240,
            ],
            [
                'code' => 'inventory.unresolved_findings',
                'category' => 'inventory',
                'name' => 'Temuan stock opname belum selesai',
                'description' => 'Mengingatkan temuan yang belum ditindaklanjuti setelah approval.',
                'severity' => 'critical',
                'recipient_permission' => 'inventory-audits.resolve',
                'lead_minutes' => 0,
                'repeat_minutes' => 720,
            ],
        ];
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return ['booking', 'rental', 'finance', 'transfer', 'maintenance', 'inventory', 'system'];
    }

    /** @return list<string> */
    public static function severities(): array
    {
        return ['info', 'warning', 'critical'];
    }
}
