<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Fleet;

use App\Domain\Fleet\Entities\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Vehicle $resource
 */
final class VehicleResource extends JsonResource
{
    public function __construct(Vehicle $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, plate: string, chassis: string, payload_kg: int, vehicle_type: string, vehicle_type_label: string, model: string|null, status: string, current_mileage: int, next_preventive_maintenance_km: int|null, needs_preventive_maintenance: bool, min_meters_per_liter: int|null, effective_min_meters_per_liter: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'plate' => $this->resource->plate,
            'chassis' => $this->resource->chassis,
            'payload_kg' => $this->resource->payloadKg,
            'vehicle_type' => $this->resource->type->value,
            'vehicle_type_label' => $this->resource->type->label(),
            'model' => $this->resource->model,
            'status' => $this->resource->status->value,
            'current_mileage' => $this->resource->currentMileage,
            'next_preventive_maintenance_km' => $this->resource->nextPreventiveMaintenanceKm,
            'needs_preventive_maintenance' => $this->resource->needsPreventiveMaintenance(),
            // Meta própria (null quando segue o tipo) e a meta que vale de fato, em metros por litro.
            'min_meters_per_liter' => $this->resource->minMetersPerLiter,
            'effective_min_meters_per_liter' => $this->resource->fuelEfficiencyTarget(),
        ];
    }
}
