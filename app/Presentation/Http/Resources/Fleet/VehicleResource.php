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
     * @return array{id: string, plate: string, chassis: string, capacity: int, class_type: string, status: string, current_mileage: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'plate' => $this->resource->plate,
            'chassis' => $this->resource->chassis,
            'capacity' => $this->resource->capacity,
            'class_type' => $this->resource->classType->value,
            'status' => $this->resource->status->value,
            'current_mileage' => $this->resource->currentMileage,
        ];
    }
}
