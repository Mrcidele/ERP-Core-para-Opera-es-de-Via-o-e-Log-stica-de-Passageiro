<?php

declare(strict_types=1);

use App\Domain\Fleet\Actions\DeleteVehicleAction;
use App\Domain\Fleet\Actions\GetVehicleAction;
use App\Domain\Fleet\Actions\ListVehiclesAction;
use App\Domain\Fleet\Actions\UpdateVehicleAction;
use App\Domain\Fleet\DTOs\UpdateVehicleDTO;
use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use App\Domain\Fleet\Exceptions\MileageDecreaseException;
use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use Tests\Fakes\InMemoryVehicleRepository;

function storedVehicle(InMemoryVehicleRepository $repository, string $plate, string $chassis, int $mileage = 100_000): Vehicle
{
    $vehicle = new Vehicle(
        id: $repository->nextIdentity(),
        plate: $plate,
        chassis: $chassis,
        capacity: 46,
        classType: VehicleClass::Executivo,
        status: VehicleStatus::Active,
        currentMileage: $mileage,
    );

    $repository->save($vehicle);

    return $vehicle;
}

function changes(Vehicle $vehicle, string ...$overrides): UpdateVehicleDTO
{
    return new UpdateVehicleDTO(
        id: $overrides['id'] ?? $vehicle->id,
        plate: $overrides['plate'] ?? $vehicle->plate,
        chassis: $overrides['chassis'] ?? $vehicle->chassis,
        capacity: 42,
        classType: VehicleClass::Leito,
        status: VehicleStatus::Maintenance,
        currentMileage: (int) ($overrides['mileage'] ?? $vehicle->currentMileage),
    );
}

beforeEach(function (): void {
    $this->repository = new InMemoryVehicleRepository;
    $this->first = storedVehicle($this->repository, 'BBB1B11', '9BWZZZ377VT004251');
    $this->second = storedVehicle($this->repository, 'AAA1A11', '9BWZZZ377VT004252');
});

it('lists vehicles ordered by plate', function (): void {
    $vehicles = (new ListVehiclesAction($this->repository))->execute();

    expect(array_map(fn (Vehicle $v): string => $v->plate, $vehicles))->toBe(['AAA1A11', 'BBB1B11']);
});

it('gets a vehicle by id', function (): void {
    expect((new GetVehicleAction($this->repository))->execute($this->first->id))->toBe($this->first);
});

it('fails to get an unknown vehicle', function (): void {
    expect(fn () => (new GetVehicleAction($this->repository))->execute('missing'))
        ->toThrow(VehicleNotFoundException::class);
});

it('updates a vehicle keeping its own plate and chassis', function (): void {
    $updated = (new UpdateVehicleAction($this->repository))->execute(changes($this->first, mileage: '150000'));

    expect($updated->id)->toBe($this->first->id)
        ->and($updated->capacity)->toBe(42)
        ->and($updated->classType)->toBe(VehicleClass::Leito)
        ->and($updated->status)->toBe(VehicleStatus::Maintenance)
        ->and($updated->currentMileage)->toBe(150_000)
        ->and($this->repository->findById($this->first->id))->toBe($updated);
});

it('rejects an update that takes the plate of another vehicle', function (): void {
    expect(fn () => (new UpdateVehicleAction($this->repository))->execute(changes($this->first, plate: 'AAA1A11')))
        ->toThrow(DuplicatePlateException::class);
});

it('rejects an update that takes the chassis of another vehicle', function (): void {
    expect(fn () => (new UpdateVehicleAction($this->repository))->execute(changes($this->first, chassis: '9BWZZZ377VT004252')))
        ->toThrow(DuplicateChassisException::class);
});

it('rejects an update that lowers the mileage', function (): void {
    expect(fn () => (new UpdateVehicleAction($this->repository))->execute(changes($this->first, mileage: '99999')))
        ->toThrow(MileageDecreaseException::class);

    expect($this->repository->findById($this->first->id))->toBe($this->first);
});

it('fails to update an unknown vehicle', function (): void {
    expect(fn () => (new UpdateVehicleAction($this->repository))->execute(changes($this->first, id: 'missing')))
        ->toThrow(VehicleNotFoundException::class);
});

it('deletes a vehicle', function (): void {
    (new DeleteVehicleAction($this->repository))->execute($this->first->id);

    expect($this->repository->findById($this->first->id))->toBeNull()
        ->and($this->repository->vehicles)->toHaveCount(1);
});

it('fails to delete an unknown vehicle', function (): void {
    expect(fn () => (new DeleteVehicleAction($this->repository))->execute('missing'))
        ->toThrow(VehicleNotFoundException::class);
});
