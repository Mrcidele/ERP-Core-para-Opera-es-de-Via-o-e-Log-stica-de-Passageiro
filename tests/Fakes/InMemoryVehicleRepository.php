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

    public function all(): array
    {
        $vehicles = array_values($this->vehicles);

        usort($vehicles, fn (Vehicle $a, Vehicle $b): int => $a->plate <=> $b->plate);

        return $vehicles;
    }

    public function findById(string $id): ?Vehicle
    {
        return $this->vehicles[$id] ?? null;
    }

    public function existsByPlate(string $plate, ?string $exceptId = null): bool
    {
        return array_any($this->vehicles, fn (Vehicle $v): bool => $v->plate === $plate && $v->id !== $exceptId);
    }

    public function existsByChassis(string $chassis, ?string $exceptId = null): bool
    {
        return array_any($this->vehicles, fn (Vehicle $v): bool => $v->chassis === $chassis && $v->id !== $exceptId);
    }

    public function save(Vehicle $vehicle): void
    {
        $this->vehicles[$vehicle->id] = $vehicle;
    }

    public function delete(string $id): void
    {
        unset($this->vehicles[$id]);
    }
}
