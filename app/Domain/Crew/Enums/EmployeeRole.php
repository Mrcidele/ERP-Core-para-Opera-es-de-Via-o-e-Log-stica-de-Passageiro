<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum EmployeeRole: string
{
    case Driver = 'driver';
    case Mechanic = 'mechanic';
    case Admin = 'admin';
}
