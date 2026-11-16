<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\UserModel;
use App\Infrastructure\Database\Models\VehicleModel;

beforeEach(function (): void {
    $this->travelTo('2026-11-14 12:00:00');
    $this->admin = actingAsRole(UserRole::Admin);
});

function lastAudit(): AuditLogModel
{
    return AuditLogModel::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
}

function scheduledTripId(): string
{
    return test()->postJson('/api/v1/operations/trips', [
        'line_id' => LineModel::factory()->create(['distance_km' => 400])->id,
        'vehicle_id' => VehicleModel::factory()->create(['status' => 'active'])->id,
        'driver_id' => EmployeeModel::factory()->create()->id,
        'departure_time' => '2026-11-20T08:00:00-03:00',
        'arrival_time' => '2026-11-20T14:30:00-03:00',
    ])->assertCreated()->json('data.id');
}

it('audits the deletion of a vehicle with who did it and from where', function (): void {
    $vehicle = VehicleModel::factory()->create(['plate' => 'ABC1D23', 'chassis' => '9BWZZZ377VT004251', 'status' => 'active']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->deleteJson("/api/v1/fleet/vehicles/{$vehicle->id}")
        ->assertNoContent();

    $log = lastAudit();

    expect($log->action)->toBe('vehicle.deleted')
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->auditable_type)->toBe('vehicle')
        ->and($log->auditable_id)->toBe($vehicle->id)
        ->and($log->old_values)->toBe(['plate' => 'ABC1D23', 'status' => 'active', 'chassis' => '9BWZZZ377VT004251'])
        ->and($log->new_values)->toBeNull()
        ->and($log->ip_address)->toBe('203.0.113.7')
        ->and($log->created_at->toDateTimeString())->toBe('2026-11-14 12:00:00');
});

it('audits the cancellation of a trip', function (): void {
    $trip = scheduledTripId();

    $this->postJson("/api/v1/operations/trips/{$trip}/cancel")->assertOk();

    $log = lastAudit();

    expect([$log->action, $log->auditable_type, $log->auditable_id, $log->old_values, $log->new_values, $log->user_id])
        ->toBe(['trip.canceled', 'trip', $trip, ['status' => 'scheduled'], ['status' => 'canceled'], $this->admin->id]);
});

it('audits the completion of a trip', function (): void {
    $trip = scheduledTripId();
    $this->postJson("/api/v1/operations/trips/{$trip}/start")->assertOk();

    // Agendar e iniciar não são operações auditadas.
    $this->assertDatabaseCount('audit_logs', 0);

    $this->postJson("/api/v1/operations/trips/{$trip}/complete")->assertOk();

    $log = lastAudit();

    expect($log->action)->toBe('trip.completed')
        ->and($log->auditable_id)->toBe($trip)
        ->and($log->old_values)->toBe(['status' => 'in_progress'])
        ->and($log->new_values['status'])->toBe('completed')
        ->and($log->new_values['distance_km'])->toBe(400);
});

it('audits the opening and the completion of a maintenance order', function (): void {
    $vehicle = VehicleModel::factory()->create(['status' => 'active']);
    $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create();

    $order = $this->postJson('/api/v1/maintenance/orders', [
        'vehicle_id' => $vehicle->id,
        'mechanic_id' => $mechanic->id,
        'type' => 'corrective',
        'description' => 'Reparo no ar-condicionado',
    ])->assertCreated()->json('data.id');

    $opened = lastAudit();

    expect($opened->action)->toBe('maintenance_order.opened')
        ->and($opened->auditable_type)->toBe('maintenance_order')
        ->and($opened->auditable_id)->toBe($order)
        ->and($opened->new_values)->toEqualCanonicalizing(['status' => 'open', 'type' => 'corrective', 'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id]);

    $this->travel(1)->hours();
    $this->patchJson("/api/v1/maintenance/orders/{$order}", ['status' => 'completed', 'cost_cents' => 45_090])->assertOk();

    $completed = lastAudit();

    expect($completed->action)->toBe('maintenance_order.completed')
        ->and($completed->auditable_id)->toBe($order)
        ->and($completed->new_values['cost_cents'])->toBe(45_090)
        ->and(AuditLogModel::query()->count())->toBe(2);
});

it('audits failed logins without a user and never stores the password', function (): void {
    app('auth')->forgetGuards();
    $account = UserModel::factory()->create(['email' => 'despachante@erp.test']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'senha-secreta-errada'])
        ->assertUnprocessable();

    $log = lastAudit();

    expect($log->action)->toBe('auth.login_failed')
        ->and($log->user_id)->toBeNull()
        ->and($log->auditable_type)->toBe('user')
        ->and($log->auditable_id)->toBe($account->id)
        ->and($log->new_values)->toBe(['email' => 'despachante@erp.test'])
        ->and($log->ip_address)->toBe('198.51.100.20')
        ->and(json_encode($log->getAttributes()))->not->toContain('senha-secreta-errada');

    $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'x'])->assertUnprocessable();

    expect(lastAudit()->auditable_id)->toBeNull()
        ->and(lastAudit()->new_values)->toBe(['email' => 'ninguem@erp.test']);
});

it('does not audit a successful login or a refused operation', function (): void {
    UserModel::factory()->create(['email' => 'despachante@erp.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'despachante@erp.test', 'password' => 'Password@123'])->assertOk();
    $this->deleteJson('/api/v1/fleet/vehicles/0193a5b0-7c1e-7000-8000-000000000000')->assertNotFound();

    $this->assertDatabaseCount('audit_logs', 0);
});

it('lists the audit records, most recent first, with the user name', function (): void {
    $first = VehicleModel::factory()->create(['plate' => 'AAA1A11']);
    $second = VehicleModel::factory()->create(['plate' => 'BBB2B22']);

    $this->deleteJson("/api/v1/fleet/vehicles/{$first->id}")->assertNoContent();
    $this->travel(1)->minutes();
    $this->deleteJson("/api/v1/fleet/vehicles/{$second->id}")->assertNoContent();

    $this->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.old_values.plate', 'AAA1A11')
        ->assertJsonPath('data.0', [
            'id' => lastAudit()->id,
            'action' => 'vehicle.deleted',
            'action_label' => 'Veículo excluído',
            'auditable_type' => 'vehicle',
            'auditable_id' => $second->id,
            'old_values' => ['plate' => 'BBB2B22', 'status' => $second->status->value, 'chassis' => $second->chassis],
            'new_values' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => '2026-11-14T12:01:00+00:00',
            'user' => ['id' => $this->admin->id, 'name' => $this->admin->name],
        ]);

    $this->getJson('/api/v1/audit-logs?per_page=1&page=2')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'vehicle.deleted')
        ->assertJsonPath('data.0.auditable_id', $first->id)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson('/api/v1/audit-logs?search=vehicle.del')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/audit-logs?search=nada-com-esse-nome')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
});

it('rejects invalid paging parameters', function (string $query, string $field): void {
    $this->getJson("/api/v1/audit-logs?{$query}")->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['per_page=0', 'per_page'],
    ['per_page=101', 'per_page'],
    ['per_page=abc', 'per_page'],
    ['page=0', 'page'],
    ['status=open', 'status'],
]);

it('shows the audit trail only to roles with audit.view', function (UserRole $role): void {
    actingAsRole($role);

    $this->getJson('/api/v1/audit-logs')->assertStatus($role === UserRole::Admin ? 200 : 403);
})->with(UserRole::cases());

it('keeps the record when the user who acted is deleted', function (): void {
    $this->deleteJson('/api/v1/fleet/vehicles/'.VehicleModel::factory()->create()->id)->assertNoContent();

    $this->admin->delete();
    actingAsRole(UserRole::Admin);

    $this->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonPath('data.0.user.name', $this->admin->name);
});

it('finds records by the action name in Portuguese, by its code, by user and by IP', function (): void {
    $vehicle = VehicleModel::factory()->create();
    $this->deleteJson("/api/v1/fleet/vehicles/{$vehicle->id}")->assertNoContent();
    $line = $this->postJson('/api/v1/operations/lines', [
        'origin' => 'Curitiba', 'destination' => 'Londrina', 'distance_km' => 380, 'estimated_duration_minutes' => 360,
    ])->json('data.id');
    $this->deleteJson("/api/v1/operations/lines/{$line}")->assertNoContent();

    $actions = fn (string $search): array => $this->getJson('/api/v1/audit-logs?search='.urlencode($search))->assertOk()->json('data.*.action');

    expect($actions('Veículo excluído'))->toBe(['vehicle.deleted'])
        ->and($actions('veiculo excluido'))->toBe(['vehicle.deleted'])
        ->and($actions('exclu'))->toEqualCanonicalizing(['vehicle.deleted', 'line.deleted'])
        ->and($actions('Linha'))->toEqualCanonicalizing(['line.created', 'line.deleted'])
        ->and($actions('line.created'))->toBe(['line.created'])
        ->and($actions($this->admin->name))->toHaveCount(3)
        ->and($actions('127.0.0'))->toHaveCount(3)
        ->and($actions('Viagem cancelada'))->toBe([]);

    $this->getJson('/api/v1/audit-logs?search=criada')->assertOk()->assertJsonPath('data.0.action_label', 'Linha criada');
});

it('shows the code itself as the label of an action the system no longer knows', function (): void {
    AuditLogModel::query()->create([
        'action' => 'legacy.action', 'auditable_type' => 'vehicle', 'auditable_id' => null,
        'old_values' => null, 'new_values' => null, 'ip_address' => '127.0.0.1', 'created_at' => now(),
    ]);

    $this->getJson('/api/v1/audit-logs')->assertOk()->assertJsonPath('data.0.action_label', 'legacy.action');
});
