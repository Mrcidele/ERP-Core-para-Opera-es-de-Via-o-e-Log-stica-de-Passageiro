<?php

declare(strict_types=1);

namespace App\Domain\Operations\Repositories;

use App\Domain\Operations\Entities\Line;

interface LineRepository
{
    public function nextIdentity(): string;

    /**
     * Linhas ordenadas por origem e destino, com as paradas.
     *
     * @return list<Line>
     */
    public function all(): array;

    /** Inclui linhas já excluídas: viagens antigas continuam apontando para elas. */
    public function findById(string $id): ?Line;

    /** Só linhas em uso (não excluídas), com as paradas. */
    public function findActive(string $id): ?Line;

    /**
     * Como findActive, mas trava a linha até o fim da transação em curso: agendamento e exclusão
     * da mesma linha passam um de cada vez, e quem chega depois já vê o que o outro gravou.
     */
    public function lockActive(string $id): ?Line;

    /** Existe viagem agendada ou em andamento nesta linha? */
    public function hasActiveTrips(string $id): bool;

    /** Grava a linha e substitui as paradas em uma única transação. */
    public function save(Line $line): void;

    public function delete(string $id): void;
}
