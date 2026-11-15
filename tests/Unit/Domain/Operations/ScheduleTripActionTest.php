<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\ScheduleTripAction;
use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\DriverLicenseExpiredException;
use App\Domain\Operations\Exceptions\DriverNotAvailableException;
use App\Domain\Operations\Exceptions\DriverRestViolationException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\InvalidTripPeriodException;
use App\Domain\Operations\Exceptions\LineNotFoundException;
use App\Domain\Operations\Exceptions\TripInThePastException;
use App\Domain\Operations\Exceptions\VehicleNotAvailableException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use Tests\Fakes\FakeCrewGateway;
use Tests\Fakes\FakeFleetGateway;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryLineRepository;
use Tests\Fakes\InMemoryTripRepository;

function tripData(
    string $departure = '2026-11-20 08:00',
    string $arrival = '2026-11-20 14:30',
    string $vehicle = 'bus-1',
    string $driver = 'driver-1',
    string $line = 'line-1',
): ScheduleTripDTO {
    $utc = new DateTimeZone('UTC');

    return new ScheduleTripDTO(
        lineId: $line,
        vehicleId: $vehicle,
        driverId: $driver,
        departureTime: new DateTimeImmutable($departure, $utc),
        arrivalTime: new DateTimeImmutable($arrival, $utc),
    );
}

beforeEach(function (): void {
    $this->trips = new InMemoryTripRepository;
    $this->lines = new InMemoryLineRepository;
    $this->lines->add(new Line('line-1', 'Curitiba', 'Ourinhos', 400, 390));

    $this->action = new ScheduleTripAction(
        $this->trips,
        $this->lines,
        new FakeFleetGateway(['bus-1', 'bus-2']),
        $this->crew = new FakeCrewGateway(['driver-1', 'driver-2']),
        new FixedClock('2026-11-13 10:00:00'),
    );
});

it('schedules a trip', function (): void {
    $trip = $this->action->execute(tripData());

    expect($trip->status)->toBe(TripStatus::Scheduled)
        ->and($trip->lineId)->toBe('line-1')
        ->and($trip->vehicleId)->toBe('bus-1')
        ->and($trip->driverId)->toBe('driver-1')
        ->and($trip->departureTime->format('Y-m-d H:i'))->toBe('2026-11-20 08:00')
        ->and($trip->arrivalTime->format('Y-m-d H:i'))->toBe('2026-11-20 14:30')
        ->and($this->trips->trips[$trip->id])->toBe($trip);
});

it('rejects a departure that is not in the future', function (string $departure): void {
    expect(fn () => $this->action->execute(tripData(departure: $departure)))
        ->toThrow(TripInThePastException::class);
})->with(['2026-11-13 09:59', '2026-11-13 10:00']);

it('rejects an arrival that is not after the departure', function (string $arrival): void {
    expect(fn () => $this->action->execute(tripData(arrival: $arrival)))
        ->toThrow(InvalidTripPeriodException::class);
})->with(['2026-11-20 08:00', '2026-11-20 07:00']);

it('rejects an unknown line', function (): void {
    expect(fn () => $this->action->execute(tripData(line: 'missing')))
        ->toThrow(LineNotFoundException::class);
});

it('rejects a vehicle that is not active, such as one in maintenance', function (): void {
    expect(fn () => $this->action->execute(tripData(vehicle: 'bus-in-maintenance')))
        ->toThrow(VehicleNotAvailableException::class);

    expect($this->trips->trips)->toBeEmpty();
});

it('rejects a driver that is inactive or not a driver', function (): void {
    expect(fn () => $this->action->execute(tripData(driver: 'inactive-driver')))
        ->toThrow(DriverNotAvailableException::class);

    expect($this->trips->trips)->toBeEmpty();
});

it('rejects overlapping trips for the same vehicle', function (string $departure, string $arrival): void {
    $this->action->execute(tripData());

    expect(fn () => $this->action->execute(tripData($departure, $arrival, driver: 'driver-2')))
        ->toThrow(VehicleScheduleConflictException::class);

    expect($this->trips->trips)->toHaveCount(1);
})->with('overlapping periods');

it('rejects overlapping trips for the same driver', function (string $departure, string $arrival): void {
    $this->action->execute(tripData());

    expect(fn () => $this->action->execute(tripData($departure, $arrival, vehicle: 'bus-2')))
        ->toThrow(DriverScheduleConflictException::class);

    expect($this->trips->trips)->toHaveCount(1);
})->with('overlapping periods');

dataset('overlapping periods', [
    'same period' => ['2026-11-20 08:00', '2026-11-20 14:30'],
    'starts during the trip' => ['2026-11-20 12:00', '2026-11-20 18:00'],
    'ends during the trip' => ['2026-11-20 05:00', '2026-11-20 08:01'],
    'inside the trip' => ['2026-11-20 09:00', '2026-11-20 10:00'],
    'around the trip' => ['2026-11-20 07:00', '2026-11-20 16:00'],
]);

it('allows back-to-back trips for the same vehicle with another driver', function (): void {
    $this->action->execute(tripData());
    $this->action->execute(tripData('2026-11-20 14:30', '2026-11-20 21:00', driver: 'driver-2'));

    expect($this->trips->trips)->toHaveCount(2);
});

it('rejects a trip that leaves the driver less than 11 hours of rest', function (string $departure, string $arrival): void {
    // Viagem existente: dia 20, das 08:00 às 14:30.
    $this->action->execute(tripData());

    expect(fn () => $this->action->execute(tripData($departure, $arrival, vehicle: 'bus-2')))
        ->toThrow(DriverRestViolationException::class, 'pelo menos 11 horas de descanso');

    expect($this->trips->trips)->toHaveCount(1);
})->with([
    'starts right after the previous trip' => ['2026-11-20 14:30', '2026-11-20 21:00'],
    'starts 10h59 after the previous trip' => ['2026-11-21 01:29', '2026-11-21 06:00'],
    'ends right before the next trip' => ['2026-11-20 02:00', '2026-11-20 08:00'],
    'ends 10h59 before the next trip' => ['2026-11-19 15:00', '2026-11-19 21:01'],
]);

it('accepts a trip with exactly 11 hours of rest before or after', function (string $departure, string $arrival): void {
    $this->action->execute(tripData());
    $this->action->execute(tripData($departure, $arrival, vehicle: 'bus-2'));

    expect($this->trips->trips)->toHaveCount(2);
})->with([
    'starts 11h after the previous trip' => ['2026-11-21 01:30', '2026-11-21 06:00'],
    'ends 11h before the next trip' => ['2026-11-19 15:00', '2026-11-19 21:00'],
]);

it('reports an overlap as a schedule conflict, not as missing rest', function (): void {
    $this->action->execute(tripData());

    expect(fn () => $this->action->execute(tripData('2026-11-20 10:00', '2026-11-20 12:00', vehicle: 'bus-2')))
        ->toThrow(DriverScheduleConflictException::class);
});

it('does not count the rest after a canceled trip or for another driver', function (): void {
    $scheduled = $this->action->execute(tripData());
    $this->trips->save(new Trip($scheduled->id, $scheduled->lineId, $scheduled->vehicleId, $scheduled->driverId, $scheduled->departureTime, $scheduled->arrivalTime, TripStatus::Canceled));

    $this->action->execute(tripData('2026-11-20 14:30', '2026-11-20 21:00'));
    $this->action->execute(tripData('2026-11-20 21:00', '2026-11-21 03:00', driver: 'driver-2'));

    expect($this->trips->trips)->toHaveCount(3);
});

it('allows the same period with another vehicle and another driver', function (): void {
    $this->action->execute(tripData());
    $this->action->execute(tripData(vehicle: 'bus-2', driver: 'driver-2'));

    expect($this->trips->trips)->toHaveCount(2);
});

it('ignores canceled trips when checking conflicts', function (): void {
    $scheduled = $this->action->execute(tripData());

    $this->trips->save(new Trip(
        $scheduled->id,
        $scheduled->lineId,
        $scheduled->vehicleId,
        $scheduled->driverId,
        $scheduled->departureTime,
        $scheduled->arrivalTime,
        TripStatus::Canceled,
    ));

    $this->action->execute(tripData());

    expect($this->trips->trips)->toHaveCount(2);
});

it('rejects a driver whose license expires before the arrival', function (): void {
    $this->crew->licenseExpirations['driver-1'] = '2026-11-20';

    // Sai dia 20 e chega dia 21 (UTC): a CNH vence no meio da viagem.
    expect(fn () => $this->action->execute(tripData('2026-11-20 22:00', '2026-11-21 04:30')))
        ->toThrow(DriverLicenseExpiredException::class);

    expect($this->trips->trips)->toBeEmpty();
});

it('accepts a driver whose license expires on the arrival day', function (): void {
    $this->crew->licenseExpirations['driver-1'] = '2026-11-21';

    $this->action->execute(tripData('2026-11-20 22:00', '2026-11-21 04:30'));

    expect($this->trips->trips)->toHaveCount(1);
});
