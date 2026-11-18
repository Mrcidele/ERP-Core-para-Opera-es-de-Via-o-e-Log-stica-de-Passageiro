<?php

declare(strict_types=1);

use App\Domain\Fleet\Actions\RegisterVehicleAction;
use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use Tests\Fakes\InMemoryVehicleRepository;

function vehicleData(string $plate = 'ABC1D23', string $chassis = '9BWZZZ377VT004251'): CreateVehicleDTO
{
    return new CreateVehicleDTO(
        plate: $plate,
        chassis: $chassis,
        payloadKg: 32_000,
        type: VehicleType::TractorUnit,
        currentMileage: 120_000,
    );
}

beforeEach(function (): void {
    $this->repository = new InMemoryVehicleRepository;
    $this->action = new RegisterVehicleAction($this->repository);
});

it('registers a vehicle as active and persists it', function (): void {
    $vehicle = $this->action->execute(vehicleData());

    expect($vehicle->status)->toBe(VehicleStatus::Active)
        ->and($vehicle->plate)->toBe('ABC1D23')
        ->and($vehicle->chassis)->toBe('9BWZZZ377VT004251')
        ->and($vehicle->payloadKg)->toBe(32_000)
        ->and($vehicle->type)->toBe(VehicleType::TractorUnit)
        ->and($vehicle->currentMileage)->toBe(120_000)
        ->and($this->repository->vehicles)->toHaveCount(1)
        ->and($this->repository->vehicles[$vehicle->id])->toBe($vehicle);
});

it('rejects a duplicated plate', function (): void {
    $this->action->execute(vehicleData());

    expect(fn () => $this->action->execute(vehicleData(chassis: '9BWZZZ377VT004252')))
        ->toThrow(DuplicatePlateException::class);

    expect($this->repository->vehicles)->toHaveCount(1);
});

it('rejects a duplicated chassis', function (): void {
    $this->action->execute(vehicleData());

    expect(fn () => $this->action->execute(vehicleData(plate: 'XYZ9876')))
        ->toThrow(DuplicateChassisException::class);

    expect($this->repository->vehicles)->toHaveCount(1);
});

it('takes the fuel target from the vehicle type unless the vehicle has its own', function (): void {
    $default = $this->action->execute(vehicleData());
    $custom = $this->action->execute(new CreateVehicleDTO('DEF2G34', '9BWZZZ377VT004259', 3_500, VehicleType::Vuc, 0, minMetersPerLiter: 6_200));

    expect([$default->minMetersPerLiter, $default->fuelEfficiencyTarget()])->toBe([null, 2_000])
        ->and([$custom->minMetersPerLiter, $custom->fuelEfficiencyTarget()])->toBe([6_200, 6_200]);
});

it('expects a lower efficiency from heavier truck types', function (VehicleType $type, int $metersPerLiter, string $label): void {
    expect($type->defaultMinMetersPerLiter())->toBe($metersPerLiter)
        ->and($type->label())->toBe($label);
})->with([
    [VehicleType::Utility, 8_000, 'Utilitário / Van'],
    [VehicleType::Vuc, 5_000, 'VUC / 3/4'],
    [VehicleType::Toco, 4_500, 'Toco'],
    [VehicleType::Truck, 3_500, 'Truck'],
    [VehicleType::Bitruck, 2_500, 'Bitruck'],
    [VehicleType::TractorUnit, 2_000, 'Cavalo mecânico'],
]);
