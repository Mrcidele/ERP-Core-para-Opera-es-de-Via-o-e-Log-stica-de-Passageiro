<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\VehicleModel;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function updatePayload(VehicleModel $vehicle, array $overrides = []): array
{
    return [
        'plate' => $vehicle->plate,
        'chassis' => $vehicle->chassis,
        'capacity' => $vehicle->capacity,
        'class_type' => $vehicle->class_type->value,
        'status' => $vehicle->status->value,
        'current_mileage' => $vehicle->current_mileage,
        ...$overrides,
    ];
}

it('lists vehicles ordered by plate', function (): void {
    VehicleModel::factory()->create(['plate' => 'BBB1B11']);
    VehicleModel::factory()->create(['plate' => 'AAA1A11']);

    $this->getJson('/api/v1/fleet/vehicles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.plate', 'AAA1A11')
        ->assertJsonPath('data.1.plate', 'BBB1B11')
        ->assertJsonStructure(['data' => [['id', 'plate', 'chassis', 'capacity', 'class_type', 'status', 'current_mileage']]]);
});

it('shows a vehicle', function (): void {
    $vehicle = VehicleModel::factory()->create();

    $this->getJson("/api/v1/fleet/vehicles/{$vehicle->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.plate', $vehicle->plate);
});

it('returns 404 for an unknown vehicle', function (string $method, string $id): void {
    $this->json($method, "/api/v1/fleet/vehicles/{$id}", $method === 'PUT' ? updatePayload(VehicleModel::factory()->make()) : [])
        ->assertNotFound();
})->with(['GET', 'PUT', 'DELETE'])->with([
    'unknown uuid' => '0193a5b0-7c1e-7000-8000-000000000000',
    'malformed id' => 'abc',
]);

it('updates a vehicle', function (): void {
    $vehicle = VehicleModel::factory()->create(['status' => 'active', 'current_mileage' => 1000]);

    $this->putJson("/api/v1/fleet/vehicles/{$vehicle->id}", updatePayload($vehicle, [
        'status' => 'maintenance',
        'current_mileage' => 2500,
    ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'maintenance')
        ->assertJsonPath('data.current_mileage', 2500);

    $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'maintenance', 'current_mileage' => 2500]);
});

it('rejects invalid input on update', function (array $overrides, string $field): void {
    $vehicle = VehicleModel::factory()->create(['current_mileage' => 1000]);

    $this->putJson("/api/v1/fleet/vehicles/{$vehicle->id}", updatePayload($vehicle, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'malformed plate' => [['plate' => 'AB12345'], 'plate'],
    'unknown status' => [['status' => 'sold'], 'status'],
    'lower mileage' => [['current_mileage' => 999], 'current_mileage'],
]);

it('rejects an update that takes the plate of another vehicle', function (): void {
    $other = VehicleModel::factory()->create();
    $vehicle = VehicleModel::factory()->create();

    $this->putJson("/api/v1/fleet/vehicles/{$vehicle->id}", updatePayload($vehicle, ['plate' => $other->plate]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plate');
});

it('deletes a vehicle', function (): void {
    $vehicle = VehicleModel::factory()->create();

    $this->deleteJson("/api/v1/fleet/vehicles/{$vehicle->id}")->assertNoContent();

    $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
});

it('hides a deleted vehicle from listing and lookup', function (): void {
    $vehicle = VehicleModel::factory()->create();
    $vehicle->delete();

    $this->getJson('/api/v1/fleet/vehicles')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/fleet/vehicles/{$vehicle->id}")->assertNotFound();
});

it('allows registering again the plate and chassis of a deleted vehicle', function (): void {
    $vehicle = VehicleModel::factory()->create();
    $vehicle->delete();

    $this->postJson('/api/v1/fleet/vehicles', [
        'plate' => $vehicle->plate,
        'chassis' => $vehicle->chassis,
        'capacity' => 46,
        'class_type' => 'executivo',
        'current_mileage' => 0,
    ])->assertCreated();

    $this->assertDatabaseCount('vehicles', 2);
});
