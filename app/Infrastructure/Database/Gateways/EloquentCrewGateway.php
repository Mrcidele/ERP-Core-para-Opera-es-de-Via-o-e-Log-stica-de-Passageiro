<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Operations\Contracts\CrewGateway;
use App\Infrastructure\Database\Models\EmployeeModel;
use Illuminate\Support\Str;

final class EloquentCrewGateway implements CrewGateway
{
    public function isActiveDriver(string $employeeId): bool
    {
        return Str::isUuid($employeeId)
            && EmployeeModel::query()
                ->whereKey($employeeId)
                ->where('role', EmployeeRole::Driver->value)
                ->where('status', EmployeeStatus::Active->value)
                ->exists();
    }
}
