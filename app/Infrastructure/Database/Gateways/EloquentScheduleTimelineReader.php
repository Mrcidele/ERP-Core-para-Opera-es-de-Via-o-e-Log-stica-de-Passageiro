<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Operations\Contracts\ScheduleTimelineReader;
use App\Domain\Operations\DTOs\ScheduleTimelineDTO;
use App\Domain\Operations\DTOs\TimelineDriverDTO;
use App\Domain\Operations\DTOs\TimelineMaintenanceDTO;
use App\Domain\Operations\DTOs\TimelineTripDTO;
use App\Domain\Operations\DTOs\TimelineVehicleDTO;
use App\Domain\Operations\Enums\TripStatus;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;

final class EloquentScheduleTimelineReader implements ScheduleTimelineReader
{
    private const PENDING_ORDERS = [MaintenanceStatus::Open->value, MaintenanceStatus::InProgress->value];

    public function read(DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): ScheduleTimelineDTO
    {
        $trips = $this->trips($from, $to, $restHours);

        $orders = MaintenanceOrderModel::query()
            ->whereIn('status', self::PENDING_ORDERS)
            ->where('opened_at', '<', $this->utc($to))
            ->orderBy('opened_at')
            ->orderBy('id')
            ->get();

        $vehicles = VehicleModel::query()
            ->where(fn (Builder $inOperation) => $inOperation
                ->where('status', VehicleStatus::Active->value)
                ->orWhereIn('id', $orders->pluck('vehicle_id')->unique()->all()))
            ->orderBy('plate')
            ->get();

        $drivers = EmployeeModel::query()
            ->where('role', EmployeeRole::Driver->value)
            ->where('status', EmployeeStatus::Active->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return new ScheduleTimelineDTO(
            from: $from,
            to: $to,
            driverRestHours: $restHours,
            vehicles: array_values($vehicles->map(fn (VehicleModel $vehicle): TimelineVehicleDTO => new TimelineVehicleDTO(
                id: $vehicle->id,
                plate: $vehicle->plate,
                status: $vehicle->status->value,
                // O descanso é do motorista: para o veículo só conta a viagem em si.
                trips: array_values(array_filter($trips, fn (TimelineTripDTO $trip): bool => $trip->vehicleId === $vehicle->id && $trip->arrivalTime > $from)),
                maintenanceOrders: array_values($orders->where('vehicle_id', $vehicle->id)->map(fn (MaintenanceOrderModel $order): TimelineMaintenanceDTO => new TimelineMaintenanceDTO(
                    id: $order->id,
                    type: $order->type->value,
                    status: $order->status->value,
                    openedAt: $order->opened_at,
                ))->all()),
            ))->all()),
            drivers: array_values($drivers->map(fn (EmployeeModel $driver): TimelineDriverDTO => new TimelineDriverDTO(
                id: $driver->id,
                name: $driver->name,
                trips: array_values(array_filter($trips, fn (TimelineTripDTO $trip): bool => $trip->driverId === $driver->id)),
            ))->all()),
        );
    }

    /**
     * Viagens não canceladas que tocam a janela, contando o descanso depois da chegada.
     *
     * @return list<TimelineTripDTO>
     */
    private function trips(DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): array
    {
        return array_values(
            TripModel::query()
                ->with(['line', 'vehicle', 'driver'])
                ->where('status', '!=', TripStatus::Canceled->value)
                ->where('departure_time', '<', $this->utc($to))
                ->where('arrival_time', '>', $this->utc($from->modify("-{$restHours} hours")))
                ->orderBy('departure_time')
                ->orderBy('id')
                ->get()
                ->map(fn (TripModel $trip): TimelineTripDTO => new TimelineTripDTO(
                    id: $trip->id,
                    status: $trip->status,
                    departureTime: $trip->departure_time,
                    arrivalTime: $trip->arrival_time,
                    restUntil: $trip->arrival_time->modify("+{$restHours} hours"),
                    lineOrigin: $trip->line->origin ?? '',
                    lineDestination: $trip->line->destination ?? '',
                    vehicleId: $trip->vehicle_id,
                    vehiclePlate: $trip->vehicle->plate ?? '',
                    driverId: $trip->driver_id,
                    driverName: $trip->driver->name ?? '',
                ))
                ->all(),
        );
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }
}
