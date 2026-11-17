<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Audit\Listeners\RecordAuditTrail;
use App\Domain\Fleet\Listeners\AddTripDistanceToVehicleMileage;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\DatabaseProbe;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\FuelLogDeleted;
use App\Domain\Shared\Events\FuelLogRecorded;
use App\Domain\Shared\Events\LineCreated;
use App\Domain\Shared\Events\LineDeleted;
use App\Domain\Shared\Events\LineUpdated;
use App\Domain\Shared\Events\LoginFailed;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Events\TripCanceled;
use App\Domain\Shared\Events\TripCompleted;
use App\Domain\Shared\Events\TripScheduled;
use App\Domain\Shared\Events\UserCreated;
use App\Domain\Shared\Events\VehicleDeleted;
use App\Infrastructure\Database\ConnectionDatabaseProbe;
use App\Infrastructure\Database\DatabaseTransactionRunner;
use App\Infrastructure\Events\LaravelEventDispatcher;
use App\Infrastructure\Support\SystemClock;
use App\Infrastructure\Support\TrustedProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        Clock::class => SystemClock::class,
        DatabaseProbe::class => ConnectionDatabaseProbe::class,
        EventDispatcher::class => LaravelEventDispatcher::class,
        TransactionRunner::class => DatabaseTransactionRunner::class,
    ];

    public function boot(): void
    {
        $proxies = config('app.trusted_proxies');
        TrustedProxies::apply(is_string($proxies) ? $proxies : null);

        // Reações entre módulos a eventos de domínio.
        Event::listen(TripCompleted::class, AddTripDistanceToVehicleMileage::class);

        // Trilha de auditoria das operações críticas.
        Event::listen(VehicleDeleted::class, [RecordAuditTrail::class, 'vehicleDeleted']);
        Event::listen(LineCreated::class, [RecordAuditTrail::class, 'lineCreated']);
        Event::listen(LineUpdated::class, [RecordAuditTrail::class, 'lineUpdated']);
        Event::listen(LineDeleted::class, [RecordAuditTrail::class, 'lineDeleted']);
        Event::listen(TripScheduled::class, [RecordAuditTrail::class, 'tripScheduled']);
        Event::listen(FuelLogRecorded::class, [RecordAuditTrail::class, 'fuelLogRecorded']);
        Event::listen(FuelLogDeleted::class, [RecordAuditTrail::class, 'fuelLogDeleted']);
        Event::listen(UserCreated::class, [RecordAuditTrail::class, 'userCreated']);
        Event::listen(TripCompleted::class, [RecordAuditTrail::class, 'tripCompleted']);
        Event::listen(TripCanceled::class, [RecordAuditTrail::class, 'tripCanceled']);
        Event::listen(MaintenanceOrderOpened::class, [RecordAuditTrail::class, 'maintenanceOrderOpened']);
        Event::listen(MaintenanceOrderCompleted::class, [RecordAuditTrail::class, 'maintenanceOrderCompleted']);
        Event::listen(LoginFailed::class, [RecordAuditTrail::class, 'loginFailed']);
    }
}
