<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\DTOs\UpdateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use Illuminate\Validation\Rule;

final class UpdateVehicleRequest extends VehicleRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::enum(VehicleStatus::class)],
        ];
    }

    public function toDTO(string $id): UpdateVehicleDTO
    {
        return new UpdateVehicleDTO(
            id: $id,
            plate: $this->string('plate')->toString(),
            chassis: $this->string('chassis')->toString(),
            payloadKg: $this->integer('payload_kg'),
            type: VehicleType::from($this->string('vehicle_type')->toString()),
            status: VehicleStatus::from($this->string('status')->toString()),
            currentMileage: $this->integer('current_mileage'),
            nextPreventiveMaintenanceKm: $this->filled('next_preventive_maintenance_km') ? $this->integer('next_preventive_maintenance_km') : null,
            minMetersPerLiter: $this->filled('min_meters_per_liter') ? $this->integer('min_meters_per_liter') : null,
            model: $this->filled('model') ? $this->string('model')->toString() : null,
        );
    }
}
