<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Operations;

use App\Domain\Operations\DTOs\ScheduleTimelineDTO;
use App\Domain\Operations\DTOs\TimelineDriverDTO;
use App\Domain\Operations\DTOs\TimelineMaintenanceDTO;
use App\Domain\Operations\DTOs\TimelineTripDTO;
use App\Domain\Operations\DTOs\TimelineVehicleDTO;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ScheduleTimelineDTO $resource
 */
final class ScheduleTimelineResource extends JsonResource
{
    public function __construct(ScheduleTimelineDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->iso($this->resource->from),
            'to' => $this->iso($this->resource->to),
            'driver_rest_hours' => $this->resource->driverRestHours,
            'vehicles' => array_map(fn (TimelineVehicleDTO $vehicle): array => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'status' => $vehicle->status,
                'trips' => array_map($this->trip(...), $vehicle->trips),
                // O bloco vai de `opened_at` até `estimated_completion_at`; sem previsão, até o fim da janela.
                'maintenance_orders' => array_map(fn (TimelineMaintenanceDTO $order): array => [
                    'id' => $order->id,
                    'type' => $order->type,
                    'status' => $order->status,
                    'opened_at' => $this->iso($order->openedAt),
                    'estimated_completion_at' => $order->estimatedCompletionAt === null ? null : $this->iso($order->estimatedCompletionAt),
                ], $vehicle->maintenanceOrders),
            ], $this->resource->vehicles),
            'drivers' => array_map(fn (TimelineDriverDTO $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'trips' => array_map($this->trip(...), $driver->trips),
            ], $this->resource->drivers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trip(TimelineTripDTO $trip): array
    {
        return [
            'id' => $trip->id,
            'status' => $trip->status->value,
            'departure_time' => $this->iso($trip->departureTime),
            'arrival_time' => $this->iso($trip->arrivalTime),
            // Fim do descanso obrigatório do motorista depois desta viagem.
            'rest_until' => $this->iso($trip->restUntil),
            'line' => ['origin' => $trip->lineOrigin, 'destination' => $trip->lineDestination],
            'vehicle' => ['id' => $trip->vehicleId, 'plate' => $trip->vehiclePlate],
            'driver' => ['id' => $trip->driverId, 'name' => $trip->driverName],
        ];
    }

    private function iso(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
