<?php

declare(strict_types=1);

namespace App\Domain\Operations\Repositories;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Exceptions\DriverRestViolationException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use DateTimeImmutable;

interface TripRepository
{
    public function nextIdentity(): string;

    /**
     * Viagens ordenadas pela partida, com linha, veículo e motorista.
     *
     * @return list<TripSummaryDTO>
     */
    public function allWithDetails(): array;

    public function findById(string $id): ?Trip;

    /**
     * Existe viagem não cancelada do veículo cujo período cruza [$from, $to)?
     * Viagens que apenas se encostam (uma termina quando a outra começa) não conflitam.
     */
    public function hasVehicleConflict(string $vehicleId, DateTimeImmutable $from, DateTimeImmutable $to): bool;

    /** Mesma regra de hasVehicleConflict, para o motorista. */
    public function hasDriverConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to): bool;

    /**
     * Existe viagem não cancelada do motorista a menos de $restHours horas de [$from, $to),
     * antes ou depois? Um intervalo de exatamente $restHours horas é permitido.
     */
    public function hasDriverRestConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): bool;

    /**
     * @throws VehicleScheduleConflictException|DriverScheduleConflictException|DriverRestViolationException quando outra gravação
     *                                                                                                       simultânea ocupou o mesmo período
     */
    public function save(Trip $trip): void;
}
