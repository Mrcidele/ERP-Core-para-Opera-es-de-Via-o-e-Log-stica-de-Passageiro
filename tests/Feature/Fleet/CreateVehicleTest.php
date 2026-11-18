<?php

declare(strict_types=1);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vehiclePayload(array $overrides = []): array
{
    return [
        'plate' => 'ABC1D23',
        'chassis' => '9BWZZZ377VT004251',
        'payload_kg' => 32000,
        'vehicle_type' => 'cavalo_mecanico',
        'current_mileage' => 120000,
        ...$overrides,
    ];
}

it('creates a vehicle', function (): void {
    $response = $this->postJson('/api/v1/fleet/vehicles', vehiclePayload());

    $response->assertCreated()
        ->assertJsonPath('data.plate', 'ABC1D23')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['id', 'plate', 'chassis', 'payload_kg', 'vehicle_type', 'status', 'current_mileage']]);

    $this->assertDatabaseHas('vehicles', ['plate' => 'ABC1D23', 'status' => 'active']);
});

it('normalizes plate and chassis before validating', function (): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['plate' => 'abc-1234', 'chassis' => '9bwzzz377vt004251']))
        ->assertCreated()
        ->assertJsonPath('data.plate', 'ABC1234')
        ->assertJsonPath('data.chassis', '9BWZZZ377VT004251');
});

it('rejects invalid input', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('vehicles', 0);
})->with([
    'malformed plate' => [['plate' => 'AB12345'], 'plate'],
    'chassis with forbidden letter' => [['chassis' => '9BWZZZ377VT00425O'], 'chassis'],
    'short chassis' => [['chassis' => '9BWZZZ377'], 'chassis'],
    'zero payload' => [['payload_kg' => 0], 'payload_kg'],
    'unknown type' => [['vehicle_type' => 'executivo'], 'vehicle_type'],
    'negative mileage' => [['current_mileage' => -1], 'current_mileage'],
    'payload above 60 t' => [['payload_kg' => 60_001], 'payload_kg'],
    'fuel target below 0.5 km/L' => [['min_meters_per_liter' => 499], 'min_meters_per_liter'],
    'fuel target above 30 km/L' => [['min_meters_per_liter' => 30_001], 'min_meters_per_liter'],
    'fuel target with fraction' => [['min_meters_per_liter' => 2200.5], 'min_meters_per_liter'],
]);

it('registers each truck type with its label and the default fuel target of the type', function (string $type, string $label, int $target): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['vehicle_type' => $type]))
        ->assertCreated()
        ->assertJsonPath('data.vehicle_type', $type)
        ->assertJsonPath('data.vehicle_type_label', $label)
        ->assertJsonPath('data.min_meters_per_liter', null)
        ->assertJsonPath('data.effective_min_meters_per_liter', $target);
})->with([
    ['utilitario', 'Utilitário / Van', 8_000],
    ['vuc', 'VUC / 3/4', 5_000],
    ['toco', 'Toco', 4_500],
    ['truck', 'Truck', 3_500],
    ['bitruck', 'Bitruck', 2_500],
    ['cavalo_mecanico', 'Cavalo mecânico', 2_000],
]);

it('stores the model of the truck, trimmed, and finds the vehicle by it', function (): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['model' => '  Scania R450 ']))
        ->assertCreated()
        ->assertJsonPath('data.model', 'Scania R450');

    $this->getJson('/api/v1/fleet/vehicles?search=r450')->assertOk()->assertJsonPath('meta.total', 1);
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['plate' => 'XYZ9K88', 'chassis' => '9BWZZZ377VT004259', 'model' => str_repeat('a', 61)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('model');
});

it('stores a fuel target of its own for the vehicle', function (): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['vehicle_type' => 'vuc', 'payload_kg' => 3_500, 'min_meters_per_liter' => 6_200]))
        ->assertCreated()
        ->assertJsonPath('data.payload_kg', 3_500)
        ->assertJsonPath('data.min_meters_per_liter', 6_200)
        ->assertJsonPath('data.effective_min_meters_per_liter', 6_200);

    $this->assertDatabaseHas('vehicles', ['plate' => 'ABC1D23', 'min_meters_per_liter' => 6_200]);
});

it('rejects a duplicated plate', function (): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload())->assertCreated();

    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['chassis' => '9BWZZZ377VT004252']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plate');

    $this->assertDatabaseCount('vehicles', 1);
});

it('rejects a duplicated chassis', function (): void {
    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload())->assertCreated();

    $this->postJson('/api/v1/fleet/vehicles', vehiclePayload(['plate' => 'XYZ9876']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('chassis');

    $this->assertDatabaseCount('vehicles', 1);
});
