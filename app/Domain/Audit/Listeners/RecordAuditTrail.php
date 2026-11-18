<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Actions\RecordAuditLogAction;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Events\FuelLogDeleted;
use App\Domain\Shared\Events\FuelLogRecorded;
use App\Domain\Shared\Events\LineCreated;
use App\Domain\Shared\Events\LineDeleted;
use App\Domain\Shared\Events\LineUpdated;
use App\Domain\Shared\Events\LoginFailed;
use App\Domain\Shared\Events\LoginSucceeded;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Events\TripCanceled;
use App\Domain\Shared\Events\TripCompleted;
use App\Domain\Shared\Events\TripScheduled;
use App\Domain\Shared\Events\UserCreated;
use App\Domain\Shared\Events\UserDeactivated;
use App\Domain\Shared\Events\UserReactivated;
use App\Domain\Shared\Events\UserUpdated;
use App\Domain\Shared\Events\VehicleDeleted;
use DateTimeImmutable;
use DateTimeZone;

/** Transforma os eventos críticos dos módulos em registros de auditoria. */
final readonly class RecordAuditTrail
{
    public function __construct(
        private RecordAuditLogAction $record,
    ) {}

    public function vehicleDeleted(VehicleDeleted $event): void
    {
        $this->record->execute(AuditAction::VehicleDeleted, 'vehicle', $event->vehicleId, [
            'plate' => $event->plate,
            'chassis' => $event->chassis,
            'status' => $event->status,
        ], null);
    }

    public function lineCreated(LineCreated $event): void
    {
        $this->record->execute(AuditAction::LineCreated, 'line', $event->lineId, null, $event->line);
    }

    public function lineUpdated(LineUpdated $event): void
    {
        $this->record->execute(AuditAction::LineUpdated, 'line', $event->lineId, $event->before, $event->after);
    }

    public function lineDeleted(LineDeleted $event): void
    {
        $this->record->execute(AuditAction::LineDeleted, 'line', $event->lineId, $event->line, null);
    }

    public function tripScheduled(TripScheduled $event): void
    {
        $this->record->execute(AuditAction::TripScheduled, 'trip', $event->tripId, null, [
            'status' => 'scheduled',
            'line_id' => $event->lineId,
            'vehicle_id' => $event->vehicleId,
            'driver_id' => $event->driverId,
            'departure_time' => $this->iso($event->departureTime),
            'arrival_time' => $this->iso($event->arrivalTime),
        ]);
    }

    public function fuelLogRecorded(FuelLogRecorded $event): void
    {
        $this->record->execute(AuditAction::FuelLogRecorded, 'fuel_log', $event->fuelLogId, null, [
            'vehicle_id' => $event->vehicleId,
            'odometer_km' => $event->odometerKm,
            'liters_milliliters' => $event->litersMilliliters,
            'total_cost_cents' => $event->totalCostCents,
            'station_name' => $event->stationName,
            'fueled_at' => $this->iso($event->fueledAt),
        ]);
    }

    public function fuelLogDeleted(FuelLogDeleted $event): void
    {
        $this->record->execute(AuditAction::FuelLogDeleted, 'fuel_log', $event->fuelLogId, [
            'vehicle_id' => $event->vehicleId,
            'odometer_km' => $event->odometerKm,
            'liters_milliliters' => $event->litersMilliliters,
            'total_cost_cents' => $event->totalCostCents,
            'station_name' => $event->stationName,
            'fueled_at' => $this->iso($event->fueledAt),
        ], null);
    }

    public function tripCompleted(TripCompleted $event): void
    {
        $this->record->execute(AuditAction::TripCompleted, 'trip', $event->tripId, ['status' => 'in_progress'], [
            'status' => 'completed',
            'vehicle_id' => $event->vehicleId,
            'distance_km' => $event->distanceKm,
        ]);
    }

    public function tripCanceled(TripCanceled $event): void
    {
        $this->record->execute(AuditAction::TripCanceled, 'trip', $event->tripId, ['status' => 'scheduled'], ['status' => 'canceled']);
    }

    public function maintenanceOrderOpened(MaintenanceOrderOpened $event): void
    {
        $this->record->execute(AuditAction::MaintenanceOrderOpened, 'maintenance_order', $event->orderId, null, [
            'status' => 'open',
            'type' => $event->type,
            'vehicle_id' => $event->vehicleId,
            'mechanic_id' => $event->mechanicId,
        ]);
    }

    public function maintenanceOrderCompleted(MaintenanceOrderCompleted $event): void
    {
        $this->record->execute(AuditAction::MaintenanceOrderCompleted, 'maintenance_order', $event->orderId, null, [
            'status' => 'completed',
            'vehicle_id' => $event->vehicleId,
            'cost_cents' => $event->costCents,
        ]);
    }

    public function userCreated(UserCreated $event): void
    {
        // A senha nunca entra na trilha: nem em texto, nem o hash.
        $this->record->execute(AuditAction::UserCreated, 'user', $event->userId, null, [
            'name' => $event->name,
            'email' => $event->email,
            'role' => $event->role,
            'channel' => $event->channel,
        ]);
    }

    public function userUpdated(UserUpdated $event): void
    {
        // A senha nunca entra na trilha: fica registrado só que ela foi redefinida.
        $after = $event->after;
        if ($event->passwordReset) {
            $after['password_reset'] = true;
        }

        $this->record->execute(AuditAction::UserUpdated, 'user', $event->userId, $event->before === [] ? null : $event->before, $after);
    }

    public function userDeactivated(UserDeactivated $event): void
    {
        $this->record->execute(AuditAction::UserDeactivated, 'user', $event->userId, ['active' => true], ['active' => false, 'email' => $event->email]);
    }

    public function userReactivated(UserReactivated $event): void
    {
        $this->record->execute(AuditAction::UserReactivated, 'user', $event->userId, ['active' => false], ['active' => true, 'email' => $event->email]);
    }

    public function loginSucceeded(LoginSucceeded $event): void
    {
        // A requisição de login ainda não é autenticada: o autor é a conta que acabou de entrar.
        $this->record->execute(AuditAction::LoginSucceeded, 'user', $event->userId, null, ['email' => $event->email], actorId: $event->userId);
    }

    public function loginFailed(LoginFailed $event): void
    {
        // A senha digitada nunca é registrada.
        $this->record->execute(AuditAction::LoginFailed, 'user', $event->userId, null, ['email' => $event->email]);
    }

    private function iso(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
