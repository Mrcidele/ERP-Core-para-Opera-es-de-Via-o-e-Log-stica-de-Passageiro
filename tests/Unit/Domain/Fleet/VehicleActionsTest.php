<?php

declare(strict_types=1);

use App\Domain\Fleet\Actions\DeleteVehicleAction;
use App\Domain\Fleet\Actions\GetVehicleAction;
use App\Domain\Fleet\Actions\ListVehiclesAction;
use App\Domain\Fleet\Actions\UpdateVehicleAction;
use App\Domain\Fleet\DTOs\UpdateVehicleDTO;
use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use App\Domain\Fleet\Exceptions\MileageDecreaseException;
use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use App\Domain\Shared\Events\VehicleDeleted;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\InMemoryVehicleRepository;
use Tests\Fakes\RecordingEventDispatcher;

function storedVehicle(InMemoryVehicleRepository $repository, string $plate, string $chassis, int $mileage = 100_000): Vehicle
{
    $vehicle = new Vehicle(
        id: $repository->nextIdentity(),
        plate: $plate,
        chassis: $chassis,
        payloadKg: 32_000,
        type: VehicleType::TractorUnit,
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
        payloadKg: 18_000,
        type: VehicleType::Bitruck,
        status: VehicleStatus::Maintenance,
        currentMileage: (int) ($overrides['mileage'] ?? $vehicle->currentMileage),
    );
}

beforeEach(function (): void {
    $this->repository = new InMemoryVehicleRepository;
    $this->events = new RecordingEventDispatcher;
    $this->first = storedVehicle($this->repository, 'BBB1B11', '9BWZZZ377VT004251');
    $this->second = storedVehicle($this->repository, 'AAA1A11', '9BWZZZ377VT004252');
});

it('lists vehicles ordered by plate', function (): void {
    $vehicles = (new ListVehiclesAction($this->repository))->execute();

    expect(array_map(fn (Vehicle $v): string => $v->plate, $vehicles->items))->toBe(['AAA1A11', 'BBB1B11'])
        ->and($vehicles->total)->toBe(2);
});

it('filters the vehicle list by search term and pages the result', function (): void {
    $list = new ListVehiclesAction($this->repository);

    expect(array_map(fn (Vehicle $v): string => $v->plate, $list->execute(new PageQuery(search: 'bbb'))->items))->toBe(['BBB1B11'])
        ->and(array_map(fn (Vehicle $v): string => $v->plate, $list->execute(new PageQuery(page: 2, perPage: 1))->items))->toBe(['BBB1B11'])
        ->and($list->execute(new PageQuery(status: 'inactive'))->total)->toBe(0);
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
        ->and($updated->payloadKg)->toBe(18_000)
        ->and($updated->type)->toBe(VehicleType::Bitruck)
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
    (new DeleteVehicleAction($this->repository, $this->events))->execute($this->first->id);

    expect($this->repository->findById($this->first->id))->toBeNull()
        ->and($this->repository->vehicles)->toHaveCount(1)
        ->and($this->events->events)->toEqual([new VehicleDeleted($this->first->id, 'BBB1B11', '9BWZZZ377VT004251', 'active')]);
});

it('fails to delete an unknown vehicle', function (): void {
    expect(fn () => (new DeleteVehicleAction($this->repository, $this->events))->execute('missing'))
        ->toThrow(VehicleNotFoundException::class);

    expect($this->events->events)->toBeEmpty();
});

it('lists only the vehicles due for preventive maintenance when asked', function (): void {
    $this->repository->save(new Vehicle('due', 'DUE1A11', '9BWZZZ377VT004299', 32_000, VehicleType::TractorUnit, VehicleStatus::Active, 100_000, 90_000));

    $list = new ListVehiclesAction($this->repository);

    expect(array_map(fn (Vehicle $v): string => $v->plate, $list->execute(onlyDueForMaintenance: true)->items))->toBe(['DUE1A11'])
        ->and($list->execute()->total)->toBe(3);
});

it('follows the default of the new type when the fuel target is cleared on update', function (): void {
    $withTarget = (new UpdateVehicleAction($this->repository))->execute(new UpdateVehicleDTO(
        $this->first->id, 'BBB1B11', '9BWZZZ377VT004251', 6_000, VehicleType::Toco, VehicleStatus::Active, 100_000, minMetersPerLiter: 5_200,
    ));
    $cleared = (new UpdateVehicleAction($this->repository))->execute(new UpdateVehicleDTO(
        $this->first->id, 'BBB1B11', '9BWZZZ377VT004251', 6_000, VehicleType::Toco, VehicleStatus::Active, 100_000,
    ));

    expect($withTarget->fuelEfficiencyTarget())->toBe(5_200)
        ->and($cleared->minMetersPerLiter)->toBeNull()
        ->and($cleared->fuelEfficiencyTarget())->toBe(4_500);
});
