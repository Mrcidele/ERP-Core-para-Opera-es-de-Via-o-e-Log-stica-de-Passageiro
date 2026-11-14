<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Operations;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TripSummaryDTO $resource
 */
final class TripSummaryResource extends JsonResource
{
    public function __construct(TripSummaryDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     departure_time: string,
     *     arrival_time: string,
     *     line: array{id: string, origin: string, destination: string, distance_km: int},
     *     vehicle: array{id: string, plate: string},
     *     driver: array{id: string, name: string},
     * }
     */
    public function toArray(Request $request): array
    {
        $utc = new DateTimeZone('UTC');
        $trip = $this->resource->trip;

        return [
            'id' => $trip->id,
            'status' => $trip->status->value,
            'departure_time' => $trip->departureTime->setTimezone($utc)->format(DATE_ATOM),
            'arrival_time' => $trip->arrivalTime->setTimezone($utc)->format(DATE_ATOM),
            'line' => [
                'id' => $trip->lineId,
                'origin' => $this->resource->lineOrigin,
                'destination' => $this->resource->lineDestination,
                'distance_km' => $this->resource->lineDistanceKm,
            ],
            'vehicle' => [
                'id' => $trip->vehicleId,
                'plate' => $this->resource->vehiclePlate,
            ],
            'driver' => [
                'id' => $trip->driverId,
                'name' => $this->resource->driverName,
            ],
        ];
    }
}
