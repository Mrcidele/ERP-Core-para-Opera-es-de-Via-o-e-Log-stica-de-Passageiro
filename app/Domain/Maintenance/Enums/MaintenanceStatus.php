<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Canceled = 'canceled';

    /** A ordem ainda mantém o veículo parado na oficina? */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::InProgress;
    }
}
