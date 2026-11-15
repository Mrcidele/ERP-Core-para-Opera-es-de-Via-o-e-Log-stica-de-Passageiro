<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Exceptions\TripNotFoundException;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\TripCanceled;

/** Cancela uma viagem agendada. */
final readonly class CancelTripAction
{
    public function __construct(
        private TripRepository $trips,
        private EventDispatcher $events,
    ) {}

    public function execute(string $tripId): Trip
    {
        $trip = ($this->trips->findById($tripId) ?? throw new TripNotFoundException($tripId))->cancel();

        $this->trips->save($trip);

        $this->events->dispatch(new TripCanceled($trip->id));

        return $trip;
    }
}
