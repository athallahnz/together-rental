<?php

namespace App\Domain\Transfers\Enums;

enum TransferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case Receiving = 'receiving';
    case Discrepancy = 'discrepancy';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function holdsInventory(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Dispatched,
            self::Receiving,
            self::Discrepancy,
        ], true);
    }
}
