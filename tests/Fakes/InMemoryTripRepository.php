<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use DateTimeImmutable;

final class InMemoryTripRepository implements TripRepository
{
    use SlicesPages;

    /** @var array<string, Trip> */
    public array $trips = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function paginateWithDetails(PageQuery $query): Page
    {
        return $this->pageOf($this->summaries($query), $query);
    }

    public function streamWithDetails(PageQuery $filters): iterable
    {
        return $this->summaries($filters);
    }

    /**
     * @return list<TripSummaryDTO>
     */
    private function summaries(PageQuery $query): array
    {
        $trips = array_values(array_filter(
            $this->trips,
            fn (Trip $trip): bool => $query->status === null || $trip->status->value === $query->status,
        ));

        usort($trips, fn (Trip $a, Trip $b): int => $a->departureTime <=> $b->departureTime);

        return array_map(
            fn (Trip $trip): TripSummaryDTO => new TripSummaryDTO($trip, 'Origem', 'Destino', 0, 'AAA0A00', 'Motorista'),
            $trips,
        );
    }

    public function findById(string $id): ?Trip
    {
        return $this->trips[$id] ?? null;
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

    public function hasDriverRestConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): bool
    {
        return $this->hasDriverConflict($driverId, $from->modify("-{$restHours} hours"), $to->modify("+{$restHours} hours"));
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
