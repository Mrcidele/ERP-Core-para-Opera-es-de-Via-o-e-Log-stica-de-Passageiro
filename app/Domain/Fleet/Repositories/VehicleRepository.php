<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Repositories;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

interface VehicleRepository
{
    public function nextIdentity(): string;

    /**
     * Veículos ordenados por placa. Busca por placa ou chassi; filtro por status.
     *
     * @return Page<Vehicle>
     */
    public function paginate(PageQuery $query): Page;

    public function findById(string $id): ?Vehicle;

    /** $exceptId ignora o próprio veículo ao validar uma edição. */
    public function existsByPlate(string $plate, ?string $exceptId = null): bool;

    public function existsByChassis(string $chassis, ?string $exceptId = null): bool;

    public function save(Vehicle $vehicle): void;

    public function delete(string $id): void;
}
