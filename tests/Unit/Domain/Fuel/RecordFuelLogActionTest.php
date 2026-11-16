<?php

declare(strict_types=1);

use App\Domain\Fuel\Actions\ListFuelLogsAction;
use App\Domain\Fuel\Actions\RecordFuelLogAction;
use App\Domain\Fuel\DTOs\RecordFuelLogDTO;
use App\Domain\Fuel\Entities\FuelLog;
use App\Domain\Fuel\Exceptions\FuelLogInTheFutureException;
use App\Domain\Fuel\Exceptions\FuelLogOutOfOrderException;
use App\Domain\Fuel\Exceptions\FuelVehicleNotFoundException;
use App\Domain\Fuel\Exceptions\OdometerRegressionException;
use App\Domain\Shared\Events\FuelLogRecorded;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\FakeFleetGateway;
use Tests\Fakes\FixedClock;
use Tests\Fakes\ImmediateTransactionRunner;
use Tests\Fakes\InMemoryFuelLogRepository;
use Tests\Fakes\RecordingEventDispatcher;

function fuelData(int $odometer, int $milliliters = 100_000, string $at = '2026-11-10 08:00', string $vehicle = 'bus-1', int $cost = 60_000): RecordFuelLogDTO
{
    return new RecordFuelLogDTO($vehicle, 'user-1', $odometer, $milliliters, $cost, 'Posto Graal', new DateTimeImmutable($at, new DateTimeZone('UTC')));
}

beforeEach(function (): void {
    $this->logs = new InMemoryFuelLogRepository;
    $this->fleet = new FakeFleetGateway(['bus-1', 'bus-2']);
    $this->fleet->mileages = ['bus-1' => 100_000, 'bus-2' => 50_000];
    $this->events = new RecordingEventDispatcher;
    $this->transaction = new ImmediateTransactionRunner;
    $this->action = new RecordFuelLogAction($this->logs, $this->fleet, new FixedClock('2026-11-13 10:00:00'), $this->events, $this->transaction);
});

it('records the first fuel log of a vehicle without an efficiency', function (): void {
    $summary = $this->action->execute(fuelData(100_000));

    expect($summary->log->id)->toBe('fuel-1')
        ->and($summary->log->recordedByUserId)->toBe('user-1')
        ->and($summary->previousOdometerKm)->toBeNull()
        ->and($summary->distanceKm())->toBeNull()
        ->and($summary->kmPerLiter())->toBeNull()
        ->and($summary->lowEfficiencyAlert())->toBeFalse()
        ->and($this->logs->logs)->toHaveCount(1)
        ->and($this->events->events)->toEqual([
            new FuelLogRecorded('fuel-1', 'bus-1', 100_000, 100_000, 60_000, 'Posto Graal', new DateTimeImmutable('2026-11-10 08:00', new DateTimeZone('UTC'))),
        ]);
});

it('computes km per liter from the distance since the previous fuel log of the same vehicle', function (): void {
    $this->action->execute(fuelData(100_000));
    // Outro veículo no meio não entra na conta.
    $this->action->execute(fuelData(50_000, vehicle: 'bus-2', at: '2026-11-10 12:00'));

    $summary = $this->action->execute(fuelData(100_600, 200_000, '2026-11-11 08:00'));

    expect($summary->previousOdometerKm)->toBe(100_000)
        ->and($summary->distanceKm())->toBe(600)
        ->and($summary->kmPerLiter())->toBe(3.0)
        ->and($summary->lowEfficiencyAlert())->toBeFalse();
});

it('flags low efficiency below 2.2 km/L', function (int $distance, int $milliliters, float $kmPerLiter, bool $alert): void {
    $this->action->execute(fuelData(100_000));

    $summary = $this->action->execute(fuelData(100_000 + $distance, $milliliters, '2026-11-11 08:00'));

    expect($summary->kmPerLiter())->toBe($kmPerLiter)
        ->and($summary->lowEfficiencyAlert())->toBe($alert);
})->with([
    'bem abaixo' => [300, 200_000, 1.5, true],
    'logo abaixo do limite' => [219, 100_000, 2.19, true],
    'exatamente no limite' => [220, 100_000, 2.2, false],
    'acima' => [350, 100_000, 3.5, false],
    'volume fracionado' => [1_000, 350_500, 2.85, false],
]);

it('has no efficiency when the vehicle did not move between two fuel logs', function (): void {
    $this->action->execute(fuelData(100_000));

    $summary = $this->action->execute(fuelData(100_000, at: '2026-11-11 08:00'));

    expect($summary->distanceKm())->toBe(0)
        ->and($summary->kmPerLiter())->toBeNull()
        ->and($summary->lowEfficiencyAlert())->toBeFalse();
});

it('refuses an odometer lower than the last fuel log of the vehicle', function (): void {
    $this->action->execute(fuelData(100_600));

    expect(fn () => $this->action->execute(fuelData(100_599, at: '2026-11-11 08:00')))
        ->toThrow(OdometerRegressionException::class, 'O hodômetro não pode ser menor que o do último abastecimento deste veículo (100600 km).')
        ->and($this->logs->logs)->toHaveCount(1)
        ->and($this->events->events)->toHaveCount(1);
});

it('accepts an odometer lower than the one of another vehicle', function (): void {
    $this->action->execute(fuelData(100_600));

    expect($this->action->execute(fuelData(50_100, vehicle: 'bus-2', at: '2026-11-11 08:00'))->log->odometerKm)->toBe(50_100);
});

it('refuses a fuel log dated before the last one of the vehicle', function (): void {
    $this->action->execute(fuelData(100_000, at: '2026-11-10 08:00'));

    expect(fn () => $this->action->execute(fuelData(100_500, at: '2026-11-10 07:59')))->toThrow(FuelLogOutOfOrderException::class);
});

it('refuses a fuel log in the future', function (): void {
    expect(fn () => $this->action->execute(fuelData(100_000, at: '2026-11-13 10:01')))->toThrow(FuelLogInTheFutureException::class)
        ->and($this->transaction->transactions)->toBe(0);
});

it('refuses an unknown vehicle', function (): void {
    expect(fn () => $this->action->execute(fuelData(100_000, vehicle: 'missing')))->toThrow(FuelVehicleNotFoundException::class)
        ->and($this->logs->logs)->toBe([]);
});

it('raises the vehicle mileage when the odometer is ahead of it, and never lowers it', function (int $odometer, int $expected): void {
    $this->action->execute(fuelData($odometer));

    expect($this->fleet->mileages['bus-1'])->toBe($expected);
})->with([
    'hodômetro à frente' => [100_750, 100_750],
    'igual' => [100_000, 100_000],
    'atrás do cadastro' => [99_000, 100_000],
]);

it('locks the vehicle inside a transaction before reading its last fuel log', function (): void {
    $this->action->execute(fuelData(100_000));

    expect($this->fleet->locked)->toBe(['bus-1'])
        ->and($this->transaction->transactions)->toBe(1);
});

it('lists the fuel logs, most recent first, one page at a time', function (): void {
    $this->action->execute(fuelData(100_000, at: '2026-11-10 08:00'));
    $this->action->execute(fuelData(100_400, at: '2026-11-11 08:00'));
    $this->action->execute(fuelData(100_900, at: '2026-11-12 08:00'));

    $page = (new ListFuelLogsAction($this->logs))->execute(new PageQuery(perPage: 2));

    expect(array_map(fn ($summary): int => $summary->log->odometerKm, $page->items))->toBe([100_900, 100_400])
        ->and($page->total)->toBe(3)
        ->and($page->items[0]->previousOdometerKm)->toBe(100_400);
});

it('keeps the efficiency threshold of heavy road vehicles', function (): void {
    expect(FuelLog::LOW_EFFICIENCY_KM_PER_LITER)->toBe(2.2);
});
