<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Operations;

use App\Domain\Operations\Entities\Trip;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Trip $resource
 */
final class TripResource extends JsonResource
{
    public function __construct(Trip $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, line_id: string, vehicle_id: string, driver_id: string, departure_time: string, arrival_time: string, status: string}
     */
    public function toArray(Request $request): array
    {
        $utc = new DateTimeZone('UTC');

        return [
            'id' => $this->resource->id,
            'line_id' => $this->resource->lineId,
            'vehicle_id' => $this->resource->vehicleId,
            'driver_id' => $this->resource->driverId,
            'departure_time' => $this->resource->departureTime->setTimezone($utc)->format(DATE_ATOM),
            'arrival_time' => $this->resource->arrivalTime->setTimezone($utc)->format(DATE_ATOM),
            'status' => $this->resource->status->value,
        ];
    }
}
