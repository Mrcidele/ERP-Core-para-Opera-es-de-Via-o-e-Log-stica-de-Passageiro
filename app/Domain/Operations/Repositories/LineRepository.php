<?php

declare(strict_types=1);

namespace App\Domain\Operations\Repositories;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Exceptions\LineHasActiveTripsException;

interface LineRepository
{
    public function nextIdentity(): string;

    /**
     * Linhas ordenadas por origem e destino, com as paradas.
     *
     * @return list<Line>
     */
    public function all(): array;

    public function exists(string $id): bool;

    /** Inclui linhas já excluídas: viagens antigas continuam apontando para elas. */
    public function findById(string $id): ?Line;

    /** Só linhas em uso (não excluídas), com as paradas. */
    public function findActive(string $id): ?Line;

    /** Existe viagem agendada ou em andamento nesta linha? */
    public function hasActiveTrips(string $id): bool;

    /** Grava a linha e substitui as paradas em uma única transação. */
    public function save(Line $line): void;

    /**
     * @throws LineHasActiveTripsException quando uma viagem foi agendada na linha no meio da exclusão
     */
    public function delete(string $id): void;
}
