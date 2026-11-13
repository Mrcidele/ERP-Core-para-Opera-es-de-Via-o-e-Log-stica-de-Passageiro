<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Infrastructure\Database\Repositories\EloquentEmployeeRepository;
use App\Infrastructure\Database\Repositories\EloquentVehicleRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EmployeeRepository::class => EloquentEmployeeRepository::class,
        VehicleRepository::class => EloquentVehicleRepository::class,
    ];
}
