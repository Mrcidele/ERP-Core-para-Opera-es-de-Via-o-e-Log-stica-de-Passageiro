<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Repositories;

use App\Domain\Fleet\Entities\Vehicle;

interface VehicleRepository
{
    public function nextIdentity(): string;

    public function existsByPlate(string $plate): bool;

    public function existsByChassis(string $chassis): bool;

    public function save(Vehicle $vehicle): void;
}
