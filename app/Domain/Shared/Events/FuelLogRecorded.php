<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use DateTimeImmutable;

final readonly class FuelLogRecorded
{
    public function __construct(
        public string $fuelLogId,
        public string $vehicleId,
        public int $odometerKm,
        public int $litersMilliliters,
        public int $totalCostCents,
        public string $stationName,
        public DateTimeImmutable $fueledAt,
        /** Rendimento do trecho em metros por litro; null no primeiro abastecimento ou sem rodagem. */
        public ?int $metersPerLiter,
        /** Meta de rendimento do veículo, em metros por litro. */
        public int $minMetersPerLiter,
        public bool $lowEfficiencyAlert,
    ) {}
}
