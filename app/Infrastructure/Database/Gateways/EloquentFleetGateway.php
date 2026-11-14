<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Operations\Contracts\FleetGateway;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Support\Str;

final class EloquentFleetGateway implements FleetGateway
{
    public function isActiveVehicle(string $vehicleId): bool
    {
        return Str::isUuid($vehicleId)
            && VehicleModel::query()->whereKey($vehicleId)->where('status', VehicleStatus::Active->value)->exists();
    }
}
