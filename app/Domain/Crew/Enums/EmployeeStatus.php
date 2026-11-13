<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
}
