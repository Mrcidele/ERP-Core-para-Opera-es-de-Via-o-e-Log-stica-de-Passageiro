<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
}
