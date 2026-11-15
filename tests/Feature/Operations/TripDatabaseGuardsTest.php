<?php

declare(strict_types=1);

use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\DriverRestViolationException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use App\Domain\Operations\Repositories\TripRepository;
use App\Infrastructure\Database\Models\EmployeeModel;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\TripModel;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Estes testes gravam direto pelo repositório, sem passar pela checagem da ScheduleTripAction,
 * que é o que acontece quando duas requisições simultâneas passam juntas por ela.
 */

beforeEach(function (): void {
    $this->trips = app(TripRepository::class);
    $this->line = LineModel::factory()->create();
    $this->vehicle = VehicleModel::factory()->create(['status' => 'active']);
    $this->driver = EmployeeModel::factory()->create();
});

function tripAt(string $departure, string $arrival, ?string $vehicleId = null, ?string $driverId = null, TripStatus $status = TripStatus::Scheduled): Trip
{
    $utc = new DateTimeZone('UTC');

    return new Trip(
        test()->trips->nextIdentity(),
        test()->line->id,
        $vehicleId ?? test()->vehicle->id,
        $driverId ?? test()->driver->id,
        new DateTimeImmutable($departure, $utc),
        new DateTimeImmutable($arrival, $utc),
        $status,
    );
}

it('refuses at the database an overlapping trip for the same vehicle', function (): void {
    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00'));

    $otherDriver = EmployeeModel::factory()->create();

    expect(fn () => $this->trips->save(tripAt('2026-11-20 13:00', '2026-11-20 18:00', driverId: $otherDriver->id)))
        ->toThrow(VehicleScheduleConflictException::class);

    // A transação continua utilizável depois da recusa.
    expect(TripModel::query()->count())->toBe(1);
});

it('refuses at the database an overlapping trip for the same driver', function (): void {
    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00'));

    $otherVehicle = VehicleModel::factory()->create();

    expect(fn () => $this->trips->save(tripAt('2026-11-20 13:00', '2026-11-20 18:00', vehicleId: $otherVehicle->id)))
        ->toThrow(DriverScheduleConflictException::class);

    expect(TripModel::query()->count())->toBe(1);
});

it('lets the database accept back-to-back trips with another driver, and canceled and deleted trips in the same period', function (): void {
    $otherDriver = EmployeeModel::factory()->create();

    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00'));
    $this->trips->save(tripAt('2026-11-20 14:00', '2026-11-20 20:00', driverId: $otherDriver->id));
    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00', status: TripStatus::Canceled));

    TripModel::query()->where('status', 'scheduled')->get()->each->delete();

    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 20:00'));

    expect(TripModel::withTrashed()->count())->toBe(4);
});

it('refuses at the database a trip that leaves the driver less than 11 hours of rest', function (string $departure, string $arrival): void {
    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00'));

    $otherVehicle = VehicleModel::factory()->create();

    expect(fn () => $this->trips->save(tripAt($departure, $arrival, vehicleId: $otherVehicle->id)))
        ->toThrow(DriverRestViolationException::class);

    expect(TripModel::query()->count())->toBe(1);
})->with([
    'right after' => ['2026-11-20 14:00', '2026-11-20 20:00'],
    '10h59 after' => ['2026-11-21 00:59', '2026-11-21 05:00'],
    '10h59 before' => ['2026-11-19 15:00', '2026-11-19 21:01'],
]);

it('lets the database accept exactly 11 hours of rest', function (): void {
    $otherVehicle = VehicleModel::factory()->create();

    $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 14:00'));
    $this->trips->save(tripAt('2026-11-21 01:00', '2026-11-21 05:00', vehicleId: $otherVehicle->id));
    $this->trips->save(tripAt('2026-11-19 15:00', '2026-11-19 21:00', vehicleId: $otherVehicle->id));

    expect(TripModel::query()->count())->toBe(3);
});

it('refuses at the database an arrival that is not after the departure', function (): void {
    expect(fn () => DB::transaction(fn () => $this->trips->save(tripAt('2026-11-20 08:00', '2026-11-20 08:00'))))
        ->toThrow(QueryException::class, 'trips_arrival_after_departure');
});

it('refuses at the database a vehicle with invalid capacity or mileage', function (array $attributes, string $constraint): void {
    expect(fn () => DB::transaction(fn () => VehicleModel::factory()->create($attributes)))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'zero capacity' => [['capacity' => 0], 'vehicles_capacity_positive'],
    'negative mileage' => [['current_mileage' => -1], 'vehicles_mileage_not_negative'],
]);
