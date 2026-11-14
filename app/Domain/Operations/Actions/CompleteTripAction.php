<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Exceptions\TripNotFoundException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\TripCompleted;

/** Conclui uma viagem em andamento e avisa os outros módulos. */
final readonly class CompleteTripAction
{
    public function __construct(
        private TripRepository $trips,
        private LineRepository $lines,
        private EventDispatcher $events,
    ) {}

    public function execute(string $tripId): Trip
    {
        $trip = ($this->trips->findById($tripId) ?? throw new TripNotFoundException($tripId))->complete();

        $this->trips->save($trip);

        $this->events->dispatch(new TripCompleted(
            tripId: $trip->id,
            vehicleId: $trip->vehicleId,
            distanceKm: $this->lines->findById($trip->lineId)->distanceKm ?? 0,
        ));

        return $trip;
    }
}
