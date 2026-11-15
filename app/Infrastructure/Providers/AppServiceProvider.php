<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Audit\Listeners\RecordAuditTrail;
use App\Domain\Fleet\Listeners\AddTripDistanceToVehicleMileage;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\LoginFailed;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Events\TripCanceled;
use App\Domain\Shared\Events\TripCompleted;
use App\Domain\Shared\Events\VehicleDeleted;
use App\Infrastructure\Events\LaravelEventDispatcher;
use App\Infrastructure\Support\SystemClock;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        Clock::class => SystemClock::class,
        EventDispatcher::class => LaravelEventDispatcher::class,
    ];

    public function boot(): void
    {
        // Reações entre módulos a eventos de domínio.
        Event::listen(TripCompleted::class, AddTripDistanceToVehicleMileage::class);

        // Trilha de auditoria das operações críticas.
        Event::listen(VehicleDeleted::class, [RecordAuditTrail::class, 'vehicleDeleted']);
        Event::listen(TripCompleted::class, [RecordAuditTrail::class, 'tripCompleted']);
        Event::listen(TripCanceled::class, [RecordAuditTrail::class, 'tripCanceled']);
        Event::listen(MaintenanceOrderOpened::class, [RecordAuditTrail::class, 'maintenanceOrderOpened']);
        Event::listen(MaintenanceOrderCompleted::class, [RecordAuditTrail::class, 'maintenanceOrderCompleted']);
        Event::listen(LoginFailed::class, [RecordAuditTrail::class, 'loginFailed']);
    }
}
