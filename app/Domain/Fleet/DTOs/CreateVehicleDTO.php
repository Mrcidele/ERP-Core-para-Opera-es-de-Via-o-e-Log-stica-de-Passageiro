<?php

declare(strict_types=1);

namespace App\Domain\Fleet\DTOs;

use App\Domain\Fleet\Enums\VehicleType;

final readonly class CreateVehicleDTO
{
    public function __construct(
        public string $plate,
        public string $chassis,
        public int $payloadKg,
        public VehicleType $type,
        public int $currentMileage,
        public ?int $nextPreventiveMaintenanceKm = null,
        /** Null segue o padrão do tipo de veículo. */
        public ?int $minMetersPerLiter = null,
        public ?string $model = null,
    ) {}
}
