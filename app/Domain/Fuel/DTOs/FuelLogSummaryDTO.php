<?php

declare(strict_types=1);

namespace App\Domain\Fuel\DTOs;

use App\Domain\Fuel\Entities\FuelLog;

/**
 * Abastecimento com a placa, quem registrou, o hodômetro do abastecimento anterior do mesmo veículo
 * e a meta de rendimento que vale para o veículo.
 */
final readonly class FuelLogSummaryDTO
{
    public function __construct(
        public FuelLog $log,
        public string $vehiclePlate,
        public string $recordedByName,
        /** Null no primeiro abastecimento do veículo. */
        public ?int $previousOdometerKm,
        /** Rendimento mínimo esperado do veículo, em metros por litro. */
        public int $minMetersPerLiter,
    ) {}

    public function distanceKm(): ?int
    {
        return $this->log->distanceSince($this->previousOdometerKm);
    }

    public function kmPerLiter(): ?float
    {
        return $this->log->kmPerLiter($this->previousOdometerKm);
    }

    public function lowEfficiencyAlert(): bool
    {
        return $this->log->hasLowEfficiency($this->previousOdometerKm, $this->minMetersPerLiter);
    }
}
