<?php

declare(strict_types=1);

use App\Domain\Audit\Actions\ListAuditLogsAction;
use App\Domain\Audit\Actions\RecordAuditLogAction;
use App\Domain\Audit\Listeners\RecordAuditTrail;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Events\LoginFailed;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Events\TripCanceled;
use App\Domain\Shared\Events\TripCompleted;
use App\Domain\Shared\Events\VehicleDeleted;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\FakeRequestContext;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryAuditLogRepository;

beforeEach(function (): void {
    $this->logs = new InMemoryAuditLogRepository;
    $this->record = new RecordAuditLogAction($this->logs, new FakeRequestContext, new FixedClock('2026-11-14 12:00:00'));
    $this->trail = new RecordAuditTrail($this->record);
});

it('records who did what, from where and when', function (): void {
    $log = $this->record->execute(AuditAction::VehicleDeleted, 'vehicle', 'bus-1', ['plate' => 'ABC1D23'], null);

    expect($log->userId)->toBe('user-1')
        ->and($log->ipAddress)->toBe('203.0.113.7')
        ->and($log->action)->toBe('vehicle.deleted')
        ->and($log->auditableType)->toBe('vehicle')
        ->and($log->auditableId)->toBe('bus-1')
        ->and($log->oldValues)->toBe(['plate' => 'ABC1D23'])
        ->and($log->newValues)->toBeNull()
        ->and($log->createdAt->format('Y-m-d H:i'))->toBe('2026-11-14 12:00')
        ->and($this->logs->logs)->toBe([$log]);
});

it('records an action without an authenticated user', function (): void {
    $record = new RecordAuditLogAction($this->logs, new FakeRequestContext(userId: null), new FixedClock('2026-11-14 12:00:00'));

    expect($record->execute(AuditAction::LoginFailed, 'user', null, null, ['email' => 'x@erp.test'])->userId)->toBeNull();
});

it('turns each critical event into an audit record', function (string $method, object $event, array $expected): void {
    $this->trail->{$method}($event);

    $log = $this->logs->logs[0];

    expect([$log->action, $log->auditableType, $log->auditableId, $log->oldValues, $log->newValues])->toBe($expected);
})->with([
    'vehicle deleted' => ['vehicleDeleted', new VehicleDeleted('bus-1', 'ABC1D23', '9BWZZZ377VT004251', 'active'), [
        'vehicle.deleted', 'vehicle', 'bus-1', ['plate' => 'ABC1D23', 'chassis' => '9BWZZZ377VT004251', 'status' => 'active'], null,
    ]],
    'trip completed' => ['tripCompleted', new TripCompleted('trip-1', 'bus-1', 400), [
        'trip.completed', 'trip', 'trip-1', ['status' => 'in_progress'], ['status' => 'completed', 'vehicle_id' => 'bus-1', 'distance_km' => 400],
    ]],
    'trip canceled' => ['tripCanceled', new TripCanceled('trip-1'), [
        'trip.canceled', 'trip', 'trip-1', ['status' => 'scheduled'], ['status' => 'canceled'],
    ]],
    'maintenance order opened' => ['maintenanceOrderOpened', new MaintenanceOrderOpened('order-1', 'bus-1', 'mechanic-1', 'corrective'), [
        'maintenance_order.opened', 'maintenance_order', 'order-1', null,
        ['status' => 'open', 'type' => 'corrective', 'vehicle_id' => 'bus-1', 'mechanic_id' => 'mechanic-1'],
    ]],
    'maintenance order completed' => ['maintenanceOrderCompleted', new MaintenanceOrderCompleted('order-1', 'bus-1', 45_090), [
        'maintenance_order.completed', 'maintenance_order', 'order-1', null,
        ['status' => 'completed', 'vehicle_id' => 'bus-1', 'cost_cents' => 45_090],
    ]],
    'failed login' => ['loginFailed', new LoginFailed('x@erp.test', 'user-9'), [
        'auth.login_failed', 'user', 'user-9', null, ['email' => 'x@erp.test'],
    ]],
]);

it('lists the most recent records first, one page at a time', function (): void {
    foreach ([AuditAction::LineCreated, AuditAction::LineUpdated, AuditAction::LineDeleted] as $action) {
        $this->record->execute($action, 'vehicle', null, null, null);
    }

    $list = new ListAuditLogsAction($this->logs);

    expect(array_map(fn ($summary): string => $summary->log->action, $list->execute(new PageQuery(perPage: 2))->items))->toBe(['line.deleted', 'line.updated'])
        ->and(array_map(fn ($summary): string => $summary->log->action, $list->execute(new PageQuery(page: 2, perPage: 2))->items))->toBe(['line.created'])
        ->and($list->execute(new PageQuery(perPage: 0))->items)->toHaveCount(1)
        ->and($list->execute(new PageQuery(perPage: 5000))->perPage)->toBe(PageQuery::MAX_PER_PAGE)
        ->and($list->execute()->total)->toBe(3)
        // A busca aceita o nome da ação em português.
        ->and(array_map(fn ($summary): string => $summary->log->action, $list->execute(new PageQuery(search: 'excluída'))->items))->toBe(['line.deleted']);
});
