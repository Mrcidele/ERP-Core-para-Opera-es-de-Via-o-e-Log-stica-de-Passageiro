<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;

beforeEach(function (): void {
    $this->travelTo('2026-11-13 10:00:00');

    $this->line = LineModel::factory()->create(['origin' => 'Curitiba', 'destination' => 'Ourinhos', 'distance_km' => 400]);
    $this->vehicle = VehicleModel::factory()->create(['status' => 'active', 'plate' => 'ABC1D23', 'current_mileage' => 100_000]);
    $this->driver = EmployeeModel::factory()->create(['name' => 'João da Silva']);
});

function scheduleTrip(string $departure = '2026-11-20T08:00:00-03:00', string $arrival = '2026-11-20T14:30:00-03:00'): string
{
    return test()->postJson('/api/v1/operations/trips', [
        'line_id' => test()->line->id,
        'vehicle_id' => test()->vehicle->id,
        'driver_id' => test()->driver->id,
        'departure_time' => $departure,
        'arrival_time' => $arrival,
    ])->assertCreated()->json('data.id');
}

it('lists trips ordered by departure with line, vehicle and driver', function (): void {
    $late = scheduleTrip('2026-11-21T08:00:00-03:00', '2026-11-21T14:30:00-03:00');
    $early = scheduleTrip();

    $this->getJson('/api/v1/operations/trips')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $early)
        ->assertJsonPath('data.1.id', $late)
        ->assertJsonPath('data.0', [
            'id' => $early,
            'status' => 'scheduled',
            'departure_time' => '2026-11-20T11:00:00+00:00',
            'arrival_time' => '2026-11-20T17:30:00+00:00',
            'line' => ['id' => $this->line->id, 'origin' => 'Curitiba', 'destination' => 'Ourinhos', 'distance_km' => 400],
            'vehicle' => ['id' => $this->vehicle->id, 'plate' => 'ABC1D23'],
            'driver' => ['id' => $this->driver->id, 'name' => 'João da Silva'],
        ]);
});

it('keeps listing a trip after its vehicle, driver or line is deleted', function (): void {
    scheduleTrip();

    $this->vehicle->delete();
    $this->driver->delete();
    $this->line->delete();

    $this->getJson('/api/v1/operations/trips')
        ->assertOk()
        ->assertJsonPath('data.0.vehicle.plate', 'ABC1D23')
        ->assertJsonPath('data.0.driver.name', 'João da Silva')
        ->assertJsonPath('data.0.line.destination', 'Ourinhos');
});

it('starts and completes a trip, adding the line distance to the vehicle mileage', function (): void {
    $trip = scheduleTrip();

    $this->postJson("/api/v1/operations/trips/{$trip}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id, 'current_mileage' => 100_000]);

    $this->postJson("/api/v1/operations/trips/{$trip}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('trips', ['id' => $trip, 'status' => 'completed']);
    $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id, 'current_mileage' => 100_400]);
});

it('cancels a scheduled trip and frees the period', function (): void {
    $trip = scheduleTrip();

    $this->postJson("/api/v1/operations/trips/{$trip}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    scheduleTrip();

    $this->assertDatabaseCount('trips', 2);
});

it('rejects invalid status transitions', function (array $before, string $transition): void {
    $trip = scheduleTrip();

    foreach ($before as $step) {
        $this->postJson("/api/v1/operations/trips/{$trip}/{$step}")->assertOk();
    }

    $status = TripModel::query()->findOrFail($trip)->status;

    $this->postJson("/api/v1/operations/trips/{$trip}/{$transition}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect(TripModel::query()->findOrFail($trip)->status)->toBe($status);
    $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id, 'current_mileage' => 100_000 + (in_array('complete', $before, true) ? 400 : 0)]);
})->with([
    'complete a scheduled trip' => [[], 'complete'],
    'start a trip in progress' => [['start'], 'start'],
    'cancel a trip in progress' => [['start'], 'cancel'],
    'complete a completed trip' => [['start', 'complete'], 'complete'],
    'start a canceled trip' => [['cancel'], 'start'],
    'cancel a canceled trip' => [['cancel'], 'cancel'],
]);

it('returns 404 for an unknown trip', function (string $transition, string $id): void {
    $this->postJson("/api/v1/operations/trips/{$id}/{$transition}")->assertNotFound();
})->with(['start', 'complete', 'cancel'])->with(['0193a5b0-7c1e-7000-8000-000000000000', 'abc']);
