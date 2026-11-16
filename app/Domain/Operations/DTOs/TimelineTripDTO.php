<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\TripStatus;
use DateTimeImmutable;

/** Uma viagem na grade de escala, com o fim do descanso obrigatório do motorista depois dela. */
final readonly class TimelineTripDTO
{
    public function __construct(
        public string $id,
        public TripStatus $status,
        public DateTimeImmutable $departureTime,
        public DateTimeImmutable $arrivalTime,
        public DateTimeImmutable $restUntil,
        public string $lineOrigin,
        public string $lineDestination,
        public string $vehicleId,
        public string $vehiclePlate,
        public string $driverId,
        public string $driverName,
    ) {}
}
