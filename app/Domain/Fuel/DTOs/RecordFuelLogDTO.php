<?php

declare(strict_types=1);

namespace App\Domain\Fuel\DTOs;

use DateTimeImmutable;

final readonly class RecordFuelLogDTO
{
    public function __construct(
        public string $vehicleId,
        public string $recordedByUserId,
        public int $odometerKm,
        public int $litersMilliliters,
        public int $totalCostCents,
        public string $stationName,
        public DateTimeImmutable $fueledAt,
    ) {}
}
