<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Maintenance\Contracts\FleetGateway as MaintenanceFleetGateway;
use App\Domain\Operations\Contracts\FleetGateway as OperationsFleetGateway;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Support\Str;

final class EloquentFleetGateway implements MaintenanceFleetGateway, OperationsFleetGateway
{
    public function isActiveVehicle(string $vehicleId): bool
    {
        return Str::isUuid($vehicleId)
            && VehicleModel::query()->whereKey($vehicleId)->where('status', VehicleStatus::Active->value)->exists();
    }

    public function vehicleExists(string $vehicleId): bool
    {
        return Str::isUuid($vehicleId) && VehicleModel::query()->whereKey($vehicleId)->exists();
    }

    public function markVehicleInMaintenance(string $vehicleId): void
    {
        $this->setStatus($vehicleId, VehicleStatus::Maintenance);
    }

    public function markVehicleActive(string $vehicleId): void
    {
        $this->setStatus($vehicleId, VehicleStatus::Active);
    }

    private function setStatus(string $vehicleId, VehicleStatus $status): void
    {
        VehicleModel::query()->whereKey($vehicleId)->update(['status' => $status->value]);
    }
}
