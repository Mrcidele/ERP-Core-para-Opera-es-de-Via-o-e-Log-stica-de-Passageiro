<?php

declare(strict_types=1);

use App\Domain\Fleet\Actions\IncreaseVehicleMileageAction;
use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Listeners\AddTripDistanceToVehicleMileage;
use App\Domain\Shared\Events\TripCompleted;
use Tests\Fakes\InMemoryVehicleRepository;

beforeEach(function (): void {
    $this->vehicles = new InMemoryVehicleRepository;
    $this->vehicles->save(new Vehicle('bus-1', 'ABC1D23', '9BWZZZ377VT004251', 46, VehicleClass::Executivo, VehicleStatus::Active, 100_000));
    $this->action = new IncreaseVehicleMileageAction($this->vehicles);
});

it('adds the distance to the vehicle mileage', function (): void {
    $vehicle = $this->action->execute('bus-1', 400);

    expect($vehicle?->currentMileage)->toBe(100_400)
        ->and($this->vehicles->findById('bus-1')?->currentMileage)->toBe(100_400)
        ->and($vehicle?->status)->toBe(VehicleStatus::Active);
});

it('does not change the mileage for a non-positive distance', function (int $distance): void {
    $this->action->execute('bus-1', $distance);

    expect($this->vehicles->findById('bus-1')?->currentMileage)->toBe(100_000);
})->with([0, -10]);

it('ignores a vehicle that no longer exists', function (): void {
    expect($this->action->execute('missing', 400))->toBeNull();
});

it('increases the mileage when a trip is completed', function (): void {
    (new AddTripDistanceToVehicleMileage($this->action))->handle(new TripCompleted('trip-1', 'bus-1', 400));

    expect($this->vehicles->findById('bus-1')?->currentMileage)->toBe(100_400);
});
