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
use App\Infrastructure\Support\InsecureConfigurationGuard;
use App\Infrastructure\Support\SystemClock;
use App\Infrastructure\Support\TrustedProxies;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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
        // Produção com APP_DEBUG ligado ou sem APP_KEY válida não atende requisições. Comandos de
        // terminal ficam de fora (o build roda `package:discover` sem chave); no contêiner, o
        // entrypoint faz a mesma checagem antes de subir.
        if (! $this->app->runningInConsole()) {
            InsecureConfigurationGuard::check($this->app->environment(), config('app.debug'), config('app.key'));
        }

        $proxies = config('app.trusted_proxies');
        TrustedProxies::apply(is_string($proxies) ? $proxies : null);

        // Em produção o TLS termina no balanceador: todo link gerado sai em https.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Teto de tempo por consulta no PostgreSQL: uma consulta pesada é cancelada antes de
        // esgotar a cota de compute do banco. O valor é validado antes de entrar no SQL.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $timeout = $event->connection->getConfig('statement_timeout');

            if ($event->connection->getDriverName() === 'pgsql' && is_string($timeout) && preg_match('/^\d{1,6}(ms|s|min)?$/', $timeout) === 1) {
                $event->connection->statement("SET statement_timeout TO '{$timeout}'");
            }
        });

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
