<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Enums;

enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
}
