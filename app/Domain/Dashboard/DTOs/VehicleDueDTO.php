<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Veículo que já alcançou a quilometragem da revisão preventiva. */
final readonly class VehicleDueDTO
{
    public function __construct(
        public string $id,
        public string $plate,
        public int $currentMileage,
        public int $nextPreventiveMaintenanceKm,
    ) {}
}
