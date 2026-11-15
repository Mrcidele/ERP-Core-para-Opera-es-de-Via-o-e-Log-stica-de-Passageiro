<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Maintenance\Contracts\CrewGateway as MaintenanceCrewGateway;
use App\Domain\Maintenance\DTOs\MechanicDTO;
use App\Domain\Operations\Contracts\CrewGateway as OperationsCrewGateway;
use App\Infrastructure\Database\Models\EmployeeModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

final class EloquentCrewGateway implements MaintenanceCrewGateway, OperationsCrewGateway
{
    public function isActiveDriver(string $employeeId): bool
    {
        return $this->isActiveWithRole($employeeId, EmployeeRole::Driver);
    }

    public function isActiveMechanic(string $employeeId): bool
    {
        return $this->isActiveWithRole($employeeId, EmployeeRole::Mechanic);
    }

    public function activeMechanics(): array
    {
        return array_values(
            EmployeeModel::query()
                ->where('role', EmployeeRole::Mechanic->value)
                ->where('status', EmployeeStatus::Active->value)
                ->orderBy('name')
                ->get()
                ->map(fn (EmployeeModel $model): MechanicDTO => new MechanicDTO($model->id, $model->name))
                ->all(),
        );
    }

    public function isDriverLicenseValidUntil(string $employeeId, DateTimeImmutable $moment): bool
    {
        return Str::isUuid($employeeId)
            && EmployeeModel::query()
                ->whereKey($employeeId)
                ->whereDate('cnh_expiration_date', '>=', $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'))
                ->exists();
    }

    private function isActiveWithRole(string $employeeId, EmployeeRole $role): bool
    {
        return Str::isUuid($employeeId)
            && EmployeeModel::query()
                ->whereKey($employeeId)
                ->where('role', $role->value)
                ->where('status', EmployeeStatus::Active->value)
                ->exists();
    }
}
