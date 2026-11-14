<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\CancelTripAction;
use App\Domain\Operations\Actions\CompleteTripAction;
use App\Domain\Operations\Actions\ListTripsAction;
use App\Domain\Operations\Actions\StartTripAction;
use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\InvalidTripTransitionException;
use App\Domain\Operations\Exceptions\TripNotFoundException;
use App\Domain\Shared\Events\TripCompleted;
use Tests\Fakes\InMemoryLineRepository;
use Tests\Fakes\InMemoryTripRepository;
use Tests\Fakes\RecordingEventDispatcher;

function storedTrip(InMemoryTripRepository $trips, TripStatus $status, string $departure = '2026-11-20 08:00'): Trip
{
    $utc = new DateTimeZone('UTC');
    $start = new DateTimeImmutable($departure, $utc);

    $trip = new Trip($trips->nextIdentity(), 'line-1', 'bus-1', 'driver-1', $start, $start->modify('+6 hours'), $status);

    $trips->save($trip);

    return $trip;
}

beforeEach(function (): void {
    $this->trips = new InMemoryTripRepository;
    $this->lines = new InMemoryLineRepository;
    $this->lines->add(new Line('line-1', 'Curitiba', 'Ourinhos', 400, 390));
    $this->events = new RecordingEventDispatcher;
});

it('starts a scheduled trip', function (): void {
    $trip = storedTrip($this->trips, TripStatus::Scheduled);

    $started = (new StartTripAction($this->trips))->execute($trip->id);

    expect($started->status)->toBe(TripStatus::InProgress)
        ->and($this->trips->findById($trip->id)?->status)->toBe(TripStatus::InProgress);
});

it('completes a trip in progress and announces it with the line distance', function (): void {
    $trip = storedTrip($this->trips, TripStatus::InProgress);

    $completed = (new CompleteTripAction($this->trips, $this->lines, $this->events))->execute($trip->id);

    expect($completed->status)->toBe(TripStatus::Completed)
        ->and($this->trips->findById($trip->id)?->status)->toBe(TripStatus::Completed)
        ->and($this->events->events)->toEqual([new TripCompleted($trip->id, 'bus-1', 400)]);
});

it('cancels a scheduled trip', function (): void {
    $trip = storedTrip($this->trips, TripStatus::Scheduled);

    expect((new CancelTripAction($this->trips))->execute($trip->id)->status)->toBe(TripStatus::Canceled);
});

it('rejects starting a trip that is not scheduled', function (TripStatus $status): void {
    $trip = storedTrip($this->trips, $status);

    expect(fn () => (new StartTripAction($this->trips))->execute($trip->id))
        ->toThrow(InvalidTripTransitionException::class);
})->with([TripStatus::InProgress, TripStatus::Completed, TripStatus::Canceled]);

it('rejects completing a trip that is not in progress and announces nothing', function (TripStatus $status): void {
    $trip = storedTrip($this->trips, $status);

    expect(fn () => (new CompleteTripAction($this->trips, $this->lines, $this->events))->execute($trip->id))
        ->toThrow(InvalidTripTransitionException::class);

    expect($this->events->events)->toBeEmpty()
        ->and($this->trips->findById($trip->id)?->status)->toBe($status);
})->with([TripStatus::Scheduled, TripStatus::Completed, TripStatus::Canceled]);

it('rejects canceling a trip that is not scheduled', function (TripStatus $status): void {
    $trip = storedTrip($this->trips, $status);

    expect(fn () => (new CancelTripAction($this->trips))->execute($trip->id))
        ->toThrow(InvalidTripTransitionException::class);
})->with([TripStatus::InProgress, TripStatus::Completed, TripStatus::Canceled]);

it('fails for an unknown trip', function (): void {
    expect(fn () => (new StartTripAction($this->trips))->execute('missing'))->toThrow(TripNotFoundException::class)
        ->and(fn () => (new CancelTripAction($this->trips))->execute('missing'))->toThrow(TripNotFoundException::class)
        ->and(fn () => (new CompleteTripAction($this->trips, $this->lines, $this->events))->execute('missing'))->toThrow(TripNotFoundException::class);
});

it('lists trips ordered by departure', function (): void {
    $late = storedTrip($this->trips, TripStatus::Scheduled, '2026-11-21 08:00');
    $early = storedTrip($this->trips, TripStatus::Scheduled, '2026-11-20 08:00');

    $result = (new ListTripsAction($this->trips))->execute();

    expect(array_map(fn ($summary): string => $summary->trip->id, $result))->toBe([$early->id, $late->id]);
});
