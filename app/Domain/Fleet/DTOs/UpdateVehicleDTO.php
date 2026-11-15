<?php

declare(strict_types=1);

namespace App\Domain\Fleet\DTOs;

use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;

final readonly class UpdateVehicleDTO
{
    public function __construct(
        public string $id,
        public string $plate,
        public string $chassis,
        public int $capacity,
        public VehicleClass $classType,
        public VehicleStatus $status,
        public int $currentMileage,
        public ?int $nextPreventiveMaintenanceKm = null,
    ) {}
}
