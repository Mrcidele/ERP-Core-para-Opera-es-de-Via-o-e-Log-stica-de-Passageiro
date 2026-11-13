<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Repositories;

use App\Domain\Fleet\Entities\Vehicle;

interface VehicleRepository
{
    public function nextIdentity(): string;

    /**
     * Veículos ordenados por placa.
     *
     * @return list<Vehicle>
     */
    public function all(): array;

    public function findById(string $id): ?Vehicle;

    /** $exceptId ignora o próprio veículo ao validar uma edição. */
    public function existsByPlate(string $plate, ?string $exceptId = null): bool;

    public function existsByChassis(string $chassis, ?string $exceptId = null): bool;

    public function save(Vehicle $vehicle): void;

    public function delete(string $id): void;
}
