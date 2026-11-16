<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Actions\RecordAuditLogAction;
use App\Domain\Shared\Enums\AuditAction;
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

    public function loginFailed(LoginFailed $event): void
    {
        // A senha digitada nunca é registrada.
        $this->record->execute(AuditAction::LoginFailed, 'user', $event->userId, null, ['email' => $event->email]);
    }
}
