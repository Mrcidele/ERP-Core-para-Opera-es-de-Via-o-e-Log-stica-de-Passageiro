<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

use DateTimeImmutable;

/** Uma das próximas viagens agendadas. */
final readonly class UpcomingTripDTO
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $departureTime,
        public DateTimeImmutable $arrivalTime,
        public string $lineOrigin,
        public string $lineDestination,
        public string $vehiclePlate,
        public string $driverName,
    ) {}
}
