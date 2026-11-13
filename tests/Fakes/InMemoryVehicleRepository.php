<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;

final class InMemoryVehicleRepository implements VehicleRepository
{
    /** @var array<string, Vehicle> */
    public array $vehicles = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function existsByPlate(string $plate): bool
    {
        return array_any($this->vehicles, fn (Vehicle $v): bool => $v->plate === $plate);
    }

    public function existsByChassis(string $chassis): bool
    {
        return array_any($this->vehicles, fn (Vehicle $v): bool => $v->chassis === $chassis);
    }

    public function save(Vehicle $vehicle): void
    {
        $this->vehicles[$vehicle->id] = $vehicle;
    }
}
