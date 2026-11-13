<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Infrastructure\Database\Repositories\EloquentVehicleRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        VehicleRepository::class => EloquentVehicleRepository::class,
    ];
}
