<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use DateTimeImmutable;

final readonly class TripScheduled
{
    public function __construct(
        public string $tripId,
        public string $lineId,
        public string $vehicleId,
        public string $driverId,
        public DateTimeImmutable $departureTime,
        public DateTimeImmutable $arrivalTime,
    ) {}
}
