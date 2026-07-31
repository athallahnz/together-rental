<?php

namespace App\Domain\Transfers\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
