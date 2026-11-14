<?php

declare(strict_types=1);

use App\Domain\Crew\Enums\EmployeeRole;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;

beforeEach(function (): void {
    $this->travelTo('2026-11-13 10:00:00');

    $this->line = LineModel::factory()->create();
    $this->vehicle = VehicleModel::factory()->create(['status' => 'active']);
    $this->driver = EmployeeModel::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tripPayload(array $overrides = []): array
{
    return [
        'line_id' => test()->line->id,
        'vehicle_id' => test()->vehicle->id,
        'driver_id' => test()->driver->id,
        'departure_time' => '2026-11-20T08:00:00-03:00',
        'arrival_time' => '2026-11-20T14:30:00-03:00',
        ...$overrides,
    ];
}

it('schedules a trip and stores the times in UTC', function (): void {
    $response = $this->postJson('/api/v1/operations/trips', tripPayload());

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'line_id', 'vehicle_id', 'driver_id', 'departure_time', 'arrival_time', 'status']])
        ->assertJsonPath('data.line_id', $this->line->id)
        ->assertJsonPath('data.vehicle_id', $this->vehicle->id)
        ->assertJsonPath('data.driver_id', $this->driver->id)
        ->assertJsonPath('data.departure_time', '2026-11-20T11:00:00+00:00')
        ->assertJsonPath('data.arrival_time', '2026-11-20T17:30:00+00:00')
        ->assertJsonPath('data.status', 'scheduled');

    $this->assertDatabaseHas('trips', [
        'vehicle_id' => $this->vehicle->id,
        'driver_id' => $this->driver->id,
        'departure_time' => '2026-11-20 11:00:00',
        'arrival_time' => '2026-11-20 17:30:00',
        'status' => 'scheduled',
    ]);
});

it('accepts the format produced by Date.toISOString in the browser', function (): void {
    $this->postJson('/api/v1/operations/trips', tripPayload([
        'departure_time' => '2026-11-20T11:00:00.000Z',
        'arrival_time' => '2026-11-20T17:30:00.000Z',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.departure_time', '2026-11-20T11:00:00+00:00');
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/operations/trips', tripPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('trips', 0);
})->with([
    'missing line' => [['line_id' => null], 'line_id'],
    'malformed vehicle id' => [['vehicle_id' => 'abc'], 'vehicle_id'],
    'malformed driver id' => [['driver_id' => 'abc'], 'driver_id'],
    'time without timezone' => [['departure_time' => '2026-11-20 08:00:00'], 'departure_time'],
    'departure in the past' => [['departure_time' => '2026-11-13T06:00:00-03:00'], 'departure_time'],
    'arrival before departure' => [['arrival_time' => '2026-11-20T07:00:00-03:00'], 'arrival_time'],
    'arrival equal to departure' => [['arrival_time' => '2026-11-20T08:00:00-03:00'], 'arrival_time'],
    'unknown line' => [['line_id' => '0193a5b0-7c1e-7000-8000-000000000000'], 'line_id'],
    'unknown vehicle' => [['vehicle_id' => '0193a5b0-7c1e-7000-8000-000000000000'], 'vehicle_id'],
    'unknown driver' => [['driver_id' => '0193a5b0-7c1e-7000-8000-000000000000'], 'driver_id'],
]);

it('rejects a vehicle that is not active', function (string $status): void {
    $this->vehicle->update(['status' => $status]);

    $this->postJson('/api/v1/operations/trips', tripPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_id');
})->with(['maintenance', 'inactive']);

it('rejects a deleted vehicle', function (): void {
    $this->vehicle->delete();

    $this->postJson('/api/v1/operations/trips', tripPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_id');
});

it('rejects a driver that is not active', function (string $status): void {
    $this->driver->update(['status' => $status]);

    $this->postJson('/api/v1/operations/trips', tripPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('driver_id');
})->with(['on_leave', 'inactive']);

it('rejects an employee who is not a driver', function (): void {
    $mechanic = EmployeeModel::factory()->withoutLicense(EmployeeRole::Mechanic)->create();

    $this->postJson('/api/v1/operations/trips', tripPayload(['driver_id' => $mechanic->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('driver_id');
});

it('rejects an overlapping trip for the same vehicle', function (): void {
    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();

    $otherDriver = EmployeeModel::factory()->create();

    $this->postJson('/api/v1/operations/trips', tripPayload([
        'driver_id' => $otherDriver->id,
        'departure_time' => '2026-11-20T12:00:00-03:00',
        'arrival_time' => '2026-11-20T18:00:00-03:00',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_id');

    $this->assertDatabaseCount('trips', 1);
});

it('rejects an overlapping trip for the same driver', function (): void {
    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();

    $otherVehicle = VehicleModel::factory()->create(['status' => 'active']);

    $this->postJson('/api/v1/operations/trips', tripPayload([
        'vehicle_id' => $otherVehicle->id,
        'departure_time' => '2026-11-20T06:00:00-03:00',
        'arrival_time' => '2026-11-20T08:30:00-03:00',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('driver_id');

    $this->assertDatabaseCount('trips', 1);
});

it('allows a trip that starts when the previous one ends', function (): void {
    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();

    $this->postJson('/api/v1/operations/trips', tripPayload([
        'departure_time' => '2026-11-20T14:30:00-03:00',
        'arrival_time' => '2026-11-20T21:00:00-03:00',
    ]))->assertCreated();

    $this->assertDatabaseCount('trips', 2);
});

it('ignores canceled trips when checking conflicts', function (): void {
    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();

    TripModel::query()->update(['status' => 'canceled']);

    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();
});

it('rejects a driver whose license expires before the arrival', function (): void {
    $this->driver->update(['cnh_expiration_date' => '2026-11-19']);

    $this->postJson('/api/v1/operations/trips', tripPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('driver_id');

    $this->assertDatabaseCount('trips', 0);
});

it('accepts a driver whose license expires on the arrival day', function (): void {
    $this->driver->update(['cnh_expiration_date' => '2026-11-20']);

    $this->postJson('/api/v1/operations/trips', tripPayload())->assertCreated();
});
