<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Operations\Contracts\CrewGateway;
use App\Domain\Operations\Contracts\FleetGateway;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Operations\Repositories\TripRepository;
use App\Infrastructure\Database\Gateways\EloquentCrewGateway;
use App\Infrastructure\Database\Gateways\EloquentFleetGateway;
use App\Infrastructure\Database\Repositories\EloquentEmployeeRepository;
use App\Infrastructure\Database\Repositories\EloquentLineRepository;
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
        FleetGateway::class => EloquentFleetGateway::class,
        CrewGateway::class => EloquentCrewGateway::class,
    ];
}
