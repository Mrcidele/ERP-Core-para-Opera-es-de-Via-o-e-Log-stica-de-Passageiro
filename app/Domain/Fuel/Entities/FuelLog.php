<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Entities;

use DateTimeImmutable;

/** Um abastecimento de um veículo. O volume é guardado em mililitros e o custo em centavos, sem frações. */
final readonly class FuelLog
{
    /** Abaixo disto o consumo de um ônibus rodoviário está fora do padrão. */
    public const LOW_EFFICIENCY_KM_PER_LITER = 2.2;

    public function __construct(
        public string $id,
        public string $vehicleId,
        public string $recordedByUserId,
        public int $odometerKm,
        public int $litersMilliliters,
        public int $totalCostCents,
        public string $stationName,
        public DateTimeImmutable $fueledAt,
    ) {}

    /** Quilômetros rodados desde o abastecimento anterior; null no primeiro abastecimento do veículo. */
    public function distanceSince(?int $previousOdometerKm): ?int
    {
        return $previousOdometerKm === null ? null : $this->odometerKm - $previousOdometerKm;
    }

    /**
     * Rendimento do trecho: quilômetros rodados desde o abastecimento anterior por litro abastecido agora.
     * Null quando não há abastecimento anterior ou o veículo não rodou entre os dois.
     */
    public function kmPerLiter(?int $previousOdometerKm): ?float
    {
        $distance = $this->distanceSince($previousOdometerKm);

        if ($distance === null || $distance <= 0) {
            return null;
        }

        return round($distance / ($this->litersMilliliters / 1000), 2);
    }

    public function hasLowEfficiency(?int $previousOdometerKm): bool
    {
        $kmPerLiter = $this->kmPerLiter($previousOdometerKm);

        return $kmPerLiter !== null && $kmPerLiter < self::LOW_EFFICIENCY_KM_PER_LITER;
    }
}
