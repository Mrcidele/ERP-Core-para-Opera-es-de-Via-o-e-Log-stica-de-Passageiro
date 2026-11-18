<?php

declare(strict_types=1);

namespace App\Domain\Fleet\DTOs;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;

final readonly class UpdateVehicleDTO
{
    public function __construct(
        public string $id,
        public string $plate,
        public string $chassis,
        public int $payloadKg,
        public VehicleType $type,
        public VehicleStatus $status,
        public int $currentMileage,
        public ?int $nextPreventiveMaintenanceKm = null,
        /** Null segue o padrão do tipo de veículo. */
        public ?int $minMetersPerLiter = null,
        public ?string $model = null,
    ) {}
}
