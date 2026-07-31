<?php

namespace App\Domain\Transfers\Enums;

enum ApprovalSide: string
{
    case Origin = 'origin';
    case Destination = 'destination';
}
