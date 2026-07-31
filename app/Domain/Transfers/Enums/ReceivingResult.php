<?php

namespace App\Domain\Transfers\Enums;

enum ReceivingResult: string
{
    case AcceptedGood = 'accepted_good';
    case AcceptedDamaged = 'accepted_damaged';
    case Incomplete = 'incomplete';
    case Missing = 'missing';
    case Rejected = 'rejected';

    public function createsDiscrepancy(): bool
    {
        return in_array($this, [self::Missing, self::Rejected], true);
    }

    public function createsMaintenance(): bool
    {
        return in_array($this, [self::AcceptedDamaged, self::Incomplete], true);
    }
}
