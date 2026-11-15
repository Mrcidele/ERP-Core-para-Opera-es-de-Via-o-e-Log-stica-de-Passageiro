<?php

declare(strict_types=1);

namespace App\Domain\Fleet\DTOs;

use App\Domain\Fleet\Enums\VehicleClass;

final readonly class CreateVehicleDTO
{
    public function __construct(
        public string $plate,
        public string $chassis,
        public int $capacity,
        public VehicleClass $classType,
        public int $currentMileage,
        public ?int $nextPreventiveMaintenanceKm = null,
    ) {}
}
