<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Gateways;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Dashboard\Contracts\DashboardReader;
use App\Domain\Dashboard\DTOs\CrewAlertsDTO;
use App\Domain\Dashboard\DTOs\ExpiringLicenseDTO;
use App\Domain\Dashboard\DTOs\FleetSummaryDTO;
use App\Domain\Dashboard\DTOs\FuelSummaryDTO;
use App\Domain\Dashboard\DTOs\MaintenanceSummaryDTO;
use App\Domain\Dashboard\DTOs\OperationsSummaryDTO;
use App\Domain\Dashboard\DTOs\UpcomingTripDTO;
use App\Domain\Dashboard\DTOs\VehicleDueDTO;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Operations\Enums\TripStatus;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\FuelLogModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;
use DateTimeImmutable;
use DateTimeZone;

final class EloquentDashboardReader implements DashboardReader
{
    public function fleet(): FleetSummaryDTO
    {
        $due = VehicleModel::query()
            ->whereNotNull('next_preventive_maintenance_km')
            ->whereColumn('current_mileage', '>=', 'next_preventive_maintenance_km')
            // Os mais atrasados primeiro.
            ->orderByRaw('current_mileage - next_preventive_maintenance_km DESC')
            ->orderBy('plate')
            ->get()
            ->map(fn (VehicleModel $vehicle): VehicleDueDTO => new VehicleDueDTO(
                id: $vehicle->id,
                plate: $vehicle->plate,
                currentMileage: $vehicle->current_mileage,
                nextPreventiveMaintenanceKm: (int) $vehicle->next_preventive_maintenance_km,
            ))
            ->all();

        return new FleetSummaryDTO(
            active: $this->vehiclesWith(VehicleStatus::Active),
            maintenance: $this->vehiclesWith(VehicleStatus::Maintenance),
            inactive: $this->vehiclesWith(VehicleStatus::Inactive),
            preventiveMaintenanceDue: array_values($due),
        );
    }

    public function operations(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): OperationsSummaryDTO
    {
        $scheduledFromNow = fn () => TripModel::query()
            ->where('status', TripStatus::Scheduled->value)
            ->where('departure_time', '>=', $this->utc($now));

        $nextTrips = $scheduledFromNow()
            ->with(['line', 'vehicle', 'driver'])
            ->orderBy('departure_time')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (TripModel $trip): UpcomingTripDTO => new UpcomingTripDTO(
                id: $trip->id,
                departureTime: $trip->departure_time->toDateTimeImmutable(),
                arrivalTime: $trip->arrival_time->toDateTimeImmutable(),
                lineOrigin: $trip->line->origin ?? '',
                lineDestination: $trip->line->destination ?? '',
                vehiclePlate: $trip->vehicle->plate ?? '',
                driverName: $trip->driver->name ?? '',
            ))
            ->all();

        return new OperationsSummaryDTO(
            inProgressNow: TripModel::query()->where('status', TripStatus::InProgress->value)->count(),
            scheduledNext24Hours: $scheduledFromNow()->where('departure_time', '<=', $this->utc($until))->count(),
            nextTrips: array_values($nextTrips),
        );
    }

    public function crewAlerts(DateTimeImmutable $today, DateTimeImmutable $until): CrewAlertsDTO
    {
        $drivers = EmployeeModel::query()
            ->where('role', EmployeeRole::Driver->value)
            ->where('status', EmployeeStatus::Active->value)
            ->whereNotNull('cnh_expiration_date')
            ->whereDate('cnh_expiration_date', '<=', $until->format('Y-m-d'))
            ->orderBy('cnh_expiration_date')
            ->orderBy('name')
            ->get()
            ->map(function (EmployeeModel $driver) use ($today): ?ExpiringLicenseDTO {
                $expiration = $driver->cnh_expiration_date?->toDateTimeImmutable();

                if ($expiration === null) {
                    return null;
                }

                $days = (int) $today->setTime(0, 0)->diff($expiration->setTime(0, 0))->format('%r%a');

                return new ExpiringLicenseDTO($driver->id, $driver->name, $expiration, $days);
            })
            ->filter()
            ->all();

        return new CrewAlertsDTO(array_values($drivers));
    }

    public function fuel(DateTimeImmutable $since): FuelSummaryDTO
    {
        $logs = FuelLogModel::query()->where('fueled_at', '>=', $this->utc($since));

        return new FuelSummaryDTO(
            since: $since,
            costCents: (int) (clone $logs)->sum('total_cost_cents'),
            litersMilliliters: (int) (clone $logs)->sum('liters_milliliters'),
            logCount: (clone $logs)->count(),
        );
    }

    public function maintenance(DateTimeImmutable $since): MaintenanceSummaryDTO
    {
        $completed = MaintenanceOrderModel::query()
            ->where('status', MaintenanceStatus::Completed->value)
            ->where('completed_at', '>=', $this->utc($since));

        return new MaintenanceSummaryDTO(
            completedCount: (clone $completed)->count(),
            completedCostCents: (int) (clone $completed)->sum('cost_cents'),
            openCount: MaintenanceOrderModel::query()->where('status', MaintenanceStatus::Open->value)->count(),
            inProgressCount: MaintenanceOrderModel::query()->where('status', MaintenanceStatus::InProgress->value)->count(),
        );
    }

    private function vehiclesWith(VehicleStatus $status): int
    {
        return VehicleModel::query()->where('status', $status->value)->count();
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }
}
