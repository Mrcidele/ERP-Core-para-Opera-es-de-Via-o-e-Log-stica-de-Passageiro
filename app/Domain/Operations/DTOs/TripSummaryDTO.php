<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Entities\Trip;

/** Viagem com os dados de rota, veículo e motorista que a listagem exibe. */
final readonly class TripSummaryDTO
{
    public function __construct(
        public Trip $trip,
        public string $lineOrigin,
        public string $lineDestination,
        public int $lineDistanceKm,
        public string $vehiclePlate,
        public string $driverName,
    ) {}
}
