<?php

namespace App\Domain\Transfers\Enums;

enum DiscrepancyResolution: string
{
    case AcceptAtDestination = 'accept_at_destination';
    case ReturnToOrigin = 'return_to_origin';
    case MarkLost = 'mark_lost';
}
