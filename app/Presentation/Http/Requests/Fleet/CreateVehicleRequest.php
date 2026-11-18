<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleType;

final class CreateVehicleRequest extends VehicleRequest
{
    public function toDTO(): CreateVehicleDTO
    {
        return new CreateVehicleDTO(
            plate: $this->string('plate')->toString(),
            chassis: $this->string('chassis')->toString(),
            payloadKg: $this->integer('payload_kg'),
            type: VehicleType::from($this->string('vehicle_type')->toString()),
            currentMileage: $this->integer('current_mileage'),
            nextPreventiveMaintenanceKm: $this->filled('next_preventive_maintenance_km') ? $this->integer('next_preventive_maintenance_km') : null,
            minMetersPerLiter: $this->filled('min_meters_per_liter') ? $this->integer('min_meters_per_liter') : null,
            model: $this->filled('model') ? $this->string('model')->toString() : null,
        );
    }
}
