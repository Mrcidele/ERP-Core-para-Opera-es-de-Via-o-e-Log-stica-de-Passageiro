<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->travelTo('2026-11-13 10:00:00');

    $this->vehicle = VehicleModel::factory()->create(['status' => 'active', 'plate' => 'ABC1D23']);
    $this->mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['name' => 'Maria Souza']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function orderPayload(array $overrides = []): array
{
    return [
        'vehicle_id' => test()->vehicle->id,
        'mechanic_id' => test()->mechanic->id,
        'type' => 'corrective',
        'description' => 'Reparo no ar-condicionado',
        ...$overrides,
    ];
}

function openOrder(): string
{
    return test()->postJson('/api/v1/maintenance/orders', orderPayload())->assertCreated()->json('data.id');
}

function vehicleStatus(): string
{
    return test()->vehicle->fresh()->status->value;
}

it('opens an order and puts the vehicle in maintenance', function (): void {
    $this->postJson('/api/v1/maintenance/orders', orderPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'vehicle_id', 'mechanic_id', 'type', 'status', 'description', 'cost_cents', 'opened_at', 'completed_at']])
        ->assertJsonPath('data.vehicle_id', $this->vehicle->id)
        ->assertJsonPath('data.mechanic_id', $this->mechanic->id)
        ->assertJsonPath('data.type', 'corrective')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.cost_cents', 0)
        ->assertJsonPath('data.opened_at', '2026-11-13T10:00:00+00:00')
        ->assertJsonPath('data.completed_at', null);

    expect(vehicleStatus())->toBe('maintenance');
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/maintenance/orders', orderPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('maintenance_orders', 0);
    expect(vehicleStatus())->toBe('active');
})->with([
    'malformed vehicle id' => [['vehicle_id' => 'abc'], 'vehicle_id'],
    'unknown vehicle' => [['vehicle_id' => '0193a5b0-7c1e-7000-8000-000000000000'], 'vehicle_id'],
    'unknown mechanic' => [['mechanic_id' => '0193a5b0-7c1e-7000-8000-000000000000'], 'mechanic_id'],
    'unknown type' => [['type' => 'cosmetic'], 'type'],
    'short description' => [['description' => 'óleo'], 'description'],
]);

it('rejects an employee who is not an active mechanic', function (Closure $employee): void {
    $this->postJson('/api/v1/maintenance/orders', orderPayload(['mechanic_id' => $employee()->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('mechanic_id');

    expect(vehicleStatus())->toBe('active');
})->with([
    'a driver' => [fn () => EmployeeModel::factory()->create()],
    'a mechanic on leave' => [fn () => EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create(['status' => 'on_leave'])],
    'a deleted mechanic' => [fn () => tap(EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create())->delete()],
]);

it('completes an order with its final cost and returns the vehicle to service', function (): void {
    $order = openOrder();

    $this->travelTo('2026-11-14 16:30:00');

    $this->patchJson("/api/v1/maintenance/orders/{$order}", ['status' => 'completed', 'cost_cents' => 45_090])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.cost_cents', 45_090)
        ->assertJsonPath('data.opened_at', '2026-11-13T10:00:00+00:00')
        ->assertJsonPath('data.completed_at', '2026-11-14T16:30:00+00:00');

    expect(vehicleStatus())->toBe('active');
});

it('keeps the vehicle in maintenance while another order is still pending', function (): void {
    $first = openOrder();
    $second = openOrder();

    $this->patchJson("/api/v1/maintenance/orders/{$first}", ['status' => 'completed', 'cost_cents' => 0])->assertOk();
    expect(vehicleStatus())->toBe('maintenance');

    $this->patchJson("/api/v1/maintenance/orders/{$second}", ['status' => 'canceled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');
    expect(vehicleStatus())->toBe('active');
});

it('rejects invalid input when closing an order', function (array $payload, string $field): void {
    $order = openOrder();

    $this->patchJson("/api/v1/maintenance/orders/{$order}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseHas('maintenance_orders', ['id' => $order, 'status' => 'open']);
    expect(vehicleStatus())->toBe('maintenance');
})->with([
    'missing status' => [['cost_cents' => 100], 'status'],
    'status that cannot be set' => [['status' => 'open'], 'status'],
    'completion without cost' => [['status' => 'completed'], 'cost_cents'],
    'negative cost' => [['status' => 'completed', 'cost_cents' => -1], 'cost_cents'],
    'cost with decimals' => [['status' => 'completed', 'cost_cents' => 10.5], 'cost_cents'],
]);

it('rejects closing an order twice', function (): void {
    $order = openOrder();

    $this->patchJson("/api/v1/maintenance/orders/{$order}", ['status' => 'completed', 'cost_cents' => 100])->assertOk();

    $this->patchJson("/api/v1/maintenance/orders/{$order}", ['status' => 'canceled'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->assertDatabaseHas('maintenance_orders', ['id' => $order, 'status' => 'completed', 'cost_cents' => 100]);
});

it('returns 404 when closing an unknown order', function (string $id): void {
    $this->patchJson("/api/v1/maintenance/orders/{$id}", ['status' => 'canceled'])->assertNotFound();
})->with(['0193a5b0-7c1e-7000-8000-000000000000', 'abc']);

it('lists orders from the most recent to the oldest with vehicle and mechanic', function (): void {
    $old = openOrder();
    $this->travelTo('2026-11-14 09:00:00');
    $recent = openOrder();

    $this->getJson('/api/v1/maintenance/orders')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.id', $old)
        ->assertJsonPath('data.0', [
            'id' => $recent,
            'type' => 'corrective',
            'status' => 'open',
            'description' => 'Reparo no ar-condicionado',
            'cost_cents' => 0,
            'opened_at' => '2026-11-14T09:00:00+00:00',
            'completed_at' => null,
            'estimated_completion_at' => null,
            'vehicle' => ['id' => $this->vehicle->id, 'plate' => 'ABC1D23'],
            'mechanic' => ['id' => $this->mechanic->id, 'name' => 'Maria Souza'],
        ]);
});

it('blocks scheduling trips while the vehicle has a pending order and frees it afterwards', function (): void {
    $trip = [
        'line_id' => LineModel::factory()->create()->id,
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => EmployeeModel::factory()->create()->id,
        'departure_time' => '2026-11-20T08:00:00-03:00',
        'arrival_time' => '2026-11-20T14:30:00-03:00',
    ];

    $order = openOrder();

    $this->postJson('/api/v1/operations/trips', $trip)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_id');

    $this->patchJson("/api/v1/maintenance/orders/{$order}", ['status' => 'completed', 'cost_cents' => 10_000])->assertOk();

    $this->postJson('/api/v1/operations/trips', $trip)->assertCreated();
});

it('refuses at the database a negative cost', function (): void {
    expect(fn () => DB::transaction(fn () => MaintenanceOrderModel::factory()->create(['cost_cents' => -1])))
        ->toThrow(QueryException::class, 'maintenance_orders_cost_not_negative');
});

it('opens an order with an estimated completion, stored in UTC', function (): void {
    $response = $this->postJson('/api/v1/maintenance/orders', orderPayload(['estimated_completion_at' => '2026-11-14T18:00:00-03:00']))
        ->assertCreated()
        ->assertJsonPath('data.estimated_completion_at', '2026-11-14T21:00:00+00:00');

    $this->assertDatabaseHas('maintenance_orders', ['id' => $response->json('data.id'), 'estimated_completion_at' => '2026-11-14 21:00:00+00']);
    $this->getJson('/api/v1/maintenance/orders')->assertOk()->assertJsonPath('data.0.estimated_completion_at', '2026-11-14T21:00:00+00:00');
});

it('opens an order without an estimate', function (?string $estimate): void {
    $this->postJson('/api/v1/maintenance/orders', orderPayload(['estimated_completion_at' => $estimate]))
        ->assertCreated()
        ->assertJsonPath('data.estimated_completion_at', null);
})->with([null, '']);

it('keeps the estimate when the order is completed', function (): void {
    $id = $this->postJson('/api/v1/maintenance/orders', orderPayload(['estimated_completion_at' => '2026-11-14T18:00:00-03:00']))->json('data.id');

    $this->patchJson("/api/v1/maintenance/orders/{$id}", ['status' => 'completed', 'cost_cents' => 45_000])
        ->assertOk()
        ->assertJsonPath('data.estimated_completion_at', '2026-11-14T21:00:00+00:00');
});

it('rejects an estimate that is not after the opening or not in ISO 8601 with time zone', function (string $estimate, string $message): void {
    $this->postJson('/api/v1/maintenance/orders', orderPayload(['estimated_completion_at' => $estimate]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['estimated_completion_at' => $message]);

    $this->assertDatabaseCount('maintenance_orders', 0);
    expect(vehicleStatus())->toBe('active');
})->with([
    'agora' => ['2026-11-13T07:00:00-03:00', 'A previsão de término deve ser posterior à abertura da ordem de serviço.'],
    'no passado' => ['2026-11-12T18:00:00-03:00', 'A previsão de término deve ser posterior à abertura da ordem de serviço.'],
    'sem fuso' => ['2026-11-14 18:00:00', 'Informe a previsão de término em ISO 8601 com fuso horário.'],
    'só a data' => ['2026-11-14', 'Informe a previsão de término em ISO 8601 com fuso horário.'],
]);

it('refuses at the database an estimate before the opening', function (): void {
    expect(fn () => MaintenanceOrderModel::factory()->create(['opened_at' => '2026-11-13 10:00:00+00', 'estimated_completion_at' => '2026-11-13 09:59:59+00']))
        ->toThrow(QueryException::class);
});
