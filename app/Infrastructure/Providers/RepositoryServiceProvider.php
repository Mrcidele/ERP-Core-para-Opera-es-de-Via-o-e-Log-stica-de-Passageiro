<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Maintenance\Contracts\CrewGateway as MaintenanceCrewGateway;
use App\Domain\Maintenance\Contracts\FleetGateway as MaintenanceFleetGateway;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Operations\Contracts\CrewGateway as OperationsCrewGateway;
use App\Domain\Operations\Contracts\FleetGateway as OperationsFleetGateway;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Operations\Repositories\TripRepository;
use App\Infrastructure\Database\Gateways\EloquentCrewGateway;
use App\Infrastructure\Database\Gateways\EloquentFleetGateway;
use App\Infrastructure\Database\Repositories\EloquentEmployeeRepository;
use App\Infrastructure\Database\Repositories\EloquentLineRepository;
use App\Infrastructure\Database\Repositories\EloquentMaintenanceOrderRepository;
use App\Infrastructure\Database\Repositories\EloquentTripRepository;
use App\Infrastructure\Database\Repositories\EloquentVehicleRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EmployeeRepository::class => EloquentEmployeeRepository::class,
        VehicleRepository::class => EloquentVehicleRepository::class,
        LineRepository::class => EloquentLineRepository::class,
        TripRepository::class => EloquentTripRepository::class,
        MaintenanceOrderRepository::class => EloquentMaintenanceOrderRepository::class,
        OperationsFleetGateway::class => EloquentFleetGateway::class,
        OperationsCrewGateway::class => EloquentCrewGateway::class,
        MaintenanceFleetGateway::class => EloquentFleetGateway::class,
        MaintenanceCrewGateway::class => EloquentCrewGateway::class,
    ];
}
