<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleClass;

final class CreateVehicleRequest extends VehicleRequest
{
    public function toDTO(): CreateVehicleDTO
    {
        return new CreateVehicleDTO(
            plate: $this->string('plate')->toString(),
            chassis: $this->string('chassis')->toString(),
            capacity: $this->integer('capacity'),
            classType: VehicleClass::from($this->string('class_type')->toString()),
            currentMileage: $this->integer('current_mileage'),
            nextPreventiveMaintenanceKm: $this->filled('next_preventive_maintenance_km') ? $this->integer('next_preventive_maintenance_km') : null,
        );
    }
}
