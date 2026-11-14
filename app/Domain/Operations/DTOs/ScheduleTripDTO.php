<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use DateTimeImmutable;

final readonly class ScheduleTripDTO
{
    public function __construct(
        public string $lineId,
        public string $vehicleId,
        public string $driverId,
        public DateTimeImmutable $departureTime,
        public DateTimeImmutable $arrivalTime,
    ) {}
}
