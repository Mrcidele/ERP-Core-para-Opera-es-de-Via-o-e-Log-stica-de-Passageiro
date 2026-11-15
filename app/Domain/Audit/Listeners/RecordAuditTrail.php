<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Actions\RecordAuditLogAction;
use App\Domain\Shared\Events\LineCreated;
use App\Domain\Shared\Events\LineDeleted;
use App\Domain\Shared\Events\LineUpdated;
use App\Domain\Shared\Events\LoginFailed;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Events\TripCanceled;
use App\Domain\Shared\Events\TripCompleted;
use App\Domain\Shared\Events\VehicleDeleted;

/** Transforma os eventos críticos dos módulos em registros de auditoria. */
final readonly class RecordAuditTrail
{
    public function __construct(
        private RecordAuditLogAction $record,
    ) {}

    public function vehicleDeleted(VehicleDeleted $event): void
    {
        $this->record->execute('vehicle.deleted', 'vehicle', $event->vehicleId, [
            'plate' => $event->plate,
            'chassis' => $event->chassis,
            'status' => $event->status,
        ], null);
    }

    public function lineCreated(LineCreated $event): void
    {
        $this->record->execute('line.created', 'line', $event->lineId, null, $event->line);
    }

    public function lineUpdated(LineUpdated $event): void
    {
        $this->record->execute('line.updated', 'line', $event->lineId, $event->before, $event->after);
    }

    public function lineDeleted(LineDeleted $event): void
    {
        $this->record->execute('line.deleted', 'line', $event->lineId, $event->line, null);
    }

    public function tripCompleted(TripCompleted $event): void
    {
        $this->record->execute('trip.completed', 'trip', $event->tripId, ['status' => 'in_progress'], [
            'status' => 'completed',
            'vehicle_id' => $event->vehicleId,
            'distance_km' => $event->distanceKm,
        ]);
    }

    public function tripCanceled(TripCanceled $event): void
    {
        $this->record->execute('trip.canceled', 'trip', $event->tripId, ['status' => 'scheduled'], ['status' => 'canceled']);
    }

    public function maintenanceOrderOpened(MaintenanceOrderOpened $event): void
    {
        $this->record->execute('maintenance_order.opened', 'maintenance_order', $event->orderId, null, [
            'status' => 'open',
            'type' => $event->type,
            'vehicle_id' => $event->vehicleId,
            'mechanic_id' => $event->mechanicId,
        ]);
    }

    public function maintenanceOrderCompleted(MaintenanceOrderCompleted $event): void
    {
        $this->record->execute('maintenance_order.completed', 'maintenance_order', $event->orderId, null, [
            'status' => 'completed',
            'vehicle_id' => $event->vehicleId,
            'cost_cents' => $event->costCents,
        ]);
    }

    public function loginFailed(LoginFailed $event): void
    {
        // A senha digitada nunca é registrada.
        $this->record->execute('auth.login_failed', 'user', $event->userId, null, ['email' => $event->email]);
    }
}
