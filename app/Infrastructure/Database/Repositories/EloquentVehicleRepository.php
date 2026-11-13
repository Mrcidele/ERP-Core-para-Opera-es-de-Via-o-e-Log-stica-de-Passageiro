<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Support\Str;

final class EloquentVehicleRepository implements VehicleRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function existsByPlate(string $plate): bool
    {
        return VehicleModel::query()->where('plate', $plate)->exists();
    }

    public function existsByChassis(string $chassis): bool
    {
        return VehicleModel::query()->where('chassis', $chassis)->exists();
    }

    public function save(Vehicle $vehicle): void
    {
        VehicleModel::query()->updateOrCreate(
            ['id' => $vehicle->id],
            [
                'plate' => $vehicle->plate,
                'chassis' => $vehicle->chassis,
                'capacity' => $vehicle->capacity,
                'class_type' => $vehicle->classType,
                'status' => $vehicle->status,
                'current_mileage' => $vehicle->currentMileage,
            ],
        );
    }
}
