<?php

declare(strict_types=1);

use App\Domain\Fleet\Actions\RegisterVehicleAction;
use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use Tests\Fakes\InMemoryVehicleRepository;

function vehicleData(string $plate = 'ABC1D23', string $chassis = '9BWZZZ377VT004251'): CreateVehicleDTO
{
    return new CreateVehicleDTO(
        plate: $plate,
        chassis: $chassis,
        capacity: 46,
        classType: VehicleClass::Executivo,
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
        ->and($vehicle->capacity)->toBe(46)
        ->and($vehicle->classType)->toBe(VehicleClass::Executivo)
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
