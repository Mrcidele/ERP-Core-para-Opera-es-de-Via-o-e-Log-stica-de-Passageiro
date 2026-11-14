<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum TripStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
