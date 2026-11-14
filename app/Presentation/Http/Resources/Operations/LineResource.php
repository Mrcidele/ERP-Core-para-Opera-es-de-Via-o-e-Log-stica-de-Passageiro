<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Operations;

use App\Domain\Operations\Entities\Line;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Line $resource
 */
final class LineResource extends JsonResource
{
    public function __construct(Line $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, origin: string, destination: string, distance_km: int, estimated_duration_minutes: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'origin' => $this->resource->origin,
            'destination' => $this->resource->destination,
            'distance_km' => $this->resource->distanceKm,
            'estimated_duration_minutes' => $this->resource->estimatedDurationMinutes,
        ];
    }
}
