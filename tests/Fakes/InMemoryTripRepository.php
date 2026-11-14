<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Repositories\TripRepository;
use DateTimeImmutable;

final class InMemoryTripRepository implements TripRepository
{
    /** @var array<string, Trip> */
    public array $trips = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function hasVehicleConflict(string $vehicleId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return array_any(
            $this->trips,
            fn (Trip $trip): bool => $trip->vehicleId === $vehicleId && $this->overlaps($trip, $from, $to),
        );
    }

    public function hasDriverConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return array_any(
            $this->trips,
            fn (Trip $trip): bool => $trip->driverId === $driverId && $this->overlaps($trip, $from, $to),
        );
    }

    public function save(Trip $trip): void
    {
        $this->trips[$trip->id] = $trip;
    }

    private function overlaps(Trip $trip, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $trip->status !== TripStatus::Canceled
            && $trip->departureTime < $to
            && $trip->arrivalTime > $from;
    }
}
