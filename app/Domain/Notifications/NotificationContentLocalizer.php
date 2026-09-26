<?php

namespace App\Domain\Notifications;

/**
 * Presentation-only locale projection of standard reminder templates.
 * The original notification title/body remain unchanged for audit, deduplication,
 * historic exports, and any manually customized content.
 */
final class NotificationContentLocalizer
{
    /** @return array{title: string, body: string} */
    public function localize(string $ruleCode, string $title, string $body, string $locale): array
    {
        if ($locale !== 'en') {
            return compact('title', 'body');
        }

        $localizedTitle = match ($ruleCode) {
            'booking.starting_soon' => $this->replace($title, '/^Booking (.+) segera dimulai$/u', 'Booking $1 starting soon'),
            'rental.due_soon' => $this->replace($title, '/^Rental (.+) mendekati jatuh tempo$/u', 'Rental $1 nearing its deadline'),
            'rental.overdue' => $this->replace($title, '/^Rental (.+) terlambat$/u', 'Rental $1 overdue'),
            'rental.balance_due' => $this->replace($title, '/^Saldo (.+) belum lunas$/u', 'Outstanding balance for $1'),
            'refund.awaiting_approval' => $this->replace($title, '/^Refund (.+) menunggu persetujuan$/u', 'Refund $1 awaiting approval'),
            'refund.awaiting_payment' => $this->replace($title, '/^Refund (.+) siap diproses$/u', 'Refund $1 ready for payout'),
            'cash.open_too_long' => $this->replace($title, '/^Sesi kas (.+) masih terbuka$/u', 'Cash session $1 still open'),
            'transfer.awaiting_approval' => $this->replace($title, '/^Pengajuan transfer menunggu approval · (.+)$/u', 'Transfer awaiting approval · $1'),
            'transfer.dispatch_due' => $this->replace($title, '/^Transfer siap diberangkatkan · (.+)$/u', 'Transfer ready for dispatch · $1'),
            'transfer.arrival_overdue' => $this->replace($title, '/^Transfer melewati estimasi tiba · (.+)$/u', 'Transfer arrival overdue · $1'),
            'maintenance.stale' => $this->replace($title, '/^Maintenance (.+) belum selesai$/u', 'Maintenance $1 unfinished'),
            'inventory.scheduled' => $this->replace($title, '/^Stock opname (.+) segera dimulai$/u', 'Stocktaking $1 starting soon'),
            'inventory.awaiting_approval' => $this->replace($title, '/^Stock opname (.+) menunggu approval$/u', 'Stocktaking $1 awaiting approval'),
            'inventory.unresolved_findings' => $this->replace($title, '/^Temuan (.+) belum ditindaklanjuti$/u', 'Findings for $1 awaiting follow-up'),
            default => $title,
        };

        $localizedBody = match ($ruleCode) {
            'booking.starting_soon' => $this->replace($body, '/^Booking (.+?) untuk (.+?) di cabang (.+?) dijadwalkan mulai (.+)\.$/u', 'Booking $1 for $2 at branch $3 is scheduled to start at $4.'),
            'rental.due_soon' => $this->rentalBody($body, false),
            'rental.overdue' => $this->rentalBody($body, true),
            'rental.balance_due' => $this->replace($body, '/^Rental (.+?) atas nama (.+?) masih memiliki saldo (.+)\.$/u', 'Rental $1 for $2 has an outstanding balance of $3.'),
            'refund.awaiting_approval', 'refund.awaiting_payment' => $this->refundBody($body),
            'cash.open_too_long' => $this->replace($body, '/^Sesi (.+?) · (.+?) di cabang (.+?) telah terbuka sejak (.+)\.$/u', 'Session $1 · $2 at branch $3 has been open since $4.'),
            'transfer.awaiting_approval', 'transfer.dispatch_due', 'transfer.arrival_overdue' => $this->replace($body, '/^Transfer (.+?) dari (.+?) ke (.+?) berstatus (.+)\.$/u', 'Transfer $1 from $2 to $3 has status $4.'),
            'maintenance.stale' => $this->replace($body, '/^Aset (.+?) di cabang (.+?) masih berstatus (.+?) sejak (.+)\.$/u', 'Asset $1 at branch $2 has had status $3 since $4.'),
            'inventory.scheduled' => $this->replace($body, '/^(.+?) di cabang (.+?) dijadwalkan pada (.+)\.$/u', '$1 at branch $2 is scheduled for $3.'),
            'inventory.awaiting_approval' => $this->replace($body, '/^(.+?) di cabang (.+?) telah diajukan dan menunggu pemeriksaan kedua\.$/u', '$1 at branch $2 was submitted and is awaiting a second review.'),
            'inventory.unresolved_findings' => $this->replace($body, '/^(.+?) di cabang (.+?) memiliki (\d+) temuan yang belum diselesaikan\.$/u', '$1 at branch $2 has $3 unresolved findings.'),
            default => $body,
        };

        return ['title' => $localizedTitle, 'body' => $localizedBody];
    }

    private function rentalBody(string $body, bool $overdue): string
    {
        $pattern = $overdue
            ? '/^Rental (.+?) atas nama (.+?) telah melewati jatuh tempo (.+?)( dengan saldo (.+))?\.$/u'
            : '/^Rental (.+?) atas nama (.+?) jatuh tempo pada (.+?)( dengan saldo (.+))?\.$/u';

        if (preg_match($pattern, $body, $parts) !== 1) {
            return $body;
        }

        $message = $overdue
            ? "Rental {$parts[1]} for {$parts[2]} was due on {$parts[3]}"
            : "Rental {$parts[1]} for {$parts[2]} is due on {$parts[3]}";

        return $message.(isset($parts[5]) && $parts[5] !== '' ? " with a balance of {$parts[5]}" : '').'.';
    }

    private function refundBody(string $body): string
    {
        if (preg_match('/^Refund (.+?) senilai (.+?) untuk cabang (.+?) berstatus (menunggu approval|telah disetujui)\.$/u', $body, $parts) !== 1) {
            return $body;
        }

        $status = $parts[4] === 'menunggu approval' ? 'awaiting approval' : 'approved';

        return "Refund {$parts[1]} for {$parts[2]} at branch {$parts[3]} is {$status}.";
    }

    private function replace(string $value, string $pattern, string $replacement): string
    {
        return preg_match($pattern, $value) === 1
            ? (preg_replace($pattern, $replacement, $value) ?? $value)
            : $value;
    }
}
