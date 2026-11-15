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
        'capacity' => 46,
        'class_type' => 'executivo',
        'current_mileage' => 120000,
        ...$overrides,
    ];
}

it('creates a vehicle', function (): void {
    $response = $this->postJson('/api/v1/fleet/vehicles', vehiclePayload());

    $response->assertCreated()
        ->assertJsonPath('data.plate', 'ABC1D23')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['id', 'plate', 'chassis', 'capacity', 'class_type', 'status', 'current_mileage']]);

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
    'zero capacity' => [['capacity' => 0], 'capacity'],
    'unknown class' => [['class_type' => 'luxo'], 'class_type'],
    'negative mileage' => [['current_mileage' => -1], 'current_mileage'],
]);

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
