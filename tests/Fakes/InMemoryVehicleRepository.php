<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final class InMemoryVehicleRepository implements VehicleRepository
{
    use SlicesPages;

    /** @var array<string, Vehicle> */
    public array $vehicles = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function paginate(PageQuery $query, bool $onlyDueForMaintenance = false): Page
    {
        $vehicles = array_values(array_filter(
            $this->vehicles,
            fn (Vehicle $vehicle): bool => (! $onlyDueForMaintenance || $vehicle->needsPreventiveMaintenance())
                && ($query->status === null || $vehicle->status->value === $query->status)
                && ($query->search === null || str_contains(mb_strtoupper($vehicle->plate.' '.$vehicle->chassis.' '.$vehicle->model), mb_strtoupper($query->search))),
        ));

        usort($vehicles, fn (Vehicle $a, Vehicle $b): int => $a->plate <=> $b->plate);

        return $this->pageOf($vehicles, $query);
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
