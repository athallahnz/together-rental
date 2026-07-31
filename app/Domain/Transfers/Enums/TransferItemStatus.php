<?php

namespace App\Domain\Transfers\Enums;

enum TransferItemStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Discrepancy = 'discrepancy';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}
