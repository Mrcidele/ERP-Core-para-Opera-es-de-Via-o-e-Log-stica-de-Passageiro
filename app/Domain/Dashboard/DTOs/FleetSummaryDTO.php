<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Distribuição da frota por status e veículos com revisão preventiva vencida. */
final readonly class FleetSummaryDTO
{
    /**
     * @param  list<VehicleDueDTO>  $preventiveMaintenanceDue
     */
    public function __construct(
        public int $active,
        public int $maintenance,
        public int $inactive,
        public array $preventiveMaintenanceDue,
    ) {}

    public function total(): int
    {
        return $this->active + $this->maintenance + $this->inactive;
    }

    /** Percentual da frota em condição de rodar, com uma casa decimal (0 quando não há veículos). */
    public function availabilityRate(): float
    {
        return $this->total() === 0 ? 0.0 : round($this->active / $this->total() * 100, 1);
    }
}
