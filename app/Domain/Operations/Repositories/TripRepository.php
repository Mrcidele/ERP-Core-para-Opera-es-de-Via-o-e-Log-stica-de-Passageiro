<?php

declare(strict_types=1);

namespace App\Domain\Operations\Repositories;

use App\Domain\Operations\Entities\Trip;
use DateTimeImmutable;

interface TripRepository
{
    public function nextIdentity(): string;

    /**
     * Existe viagem não cancelada do veículo cujo período cruza [$from, $to)?
     * Viagens que apenas se encostam (uma termina quando a outra começa) não conflitam.
     */
    public function hasVehicleConflict(string $vehicleId, DateTimeImmutable $from, DateTimeImmutable $to): bool;

    /** Mesma regra de hasVehicleConflict, para o motorista. */
    public function hasDriverConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to): bool;

    public function save(Trip $trip): void;
}
