<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class VehicleDeleted
{
    public function __construct(
        public string $vehicleId,
        public string $plate,
        public string $chassis,
        public string $status,
    ) {}
}
