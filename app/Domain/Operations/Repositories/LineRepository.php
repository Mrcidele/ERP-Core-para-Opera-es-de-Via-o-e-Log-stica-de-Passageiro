<?php

declare(strict_types=1);

namespace App\Domain\Operations\Repositories;

use App\Domain\Operations\Entities\Line;

interface LineRepository
{
    /**
     * Linhas ordenadas por origem e destino.
     *
     * @return list<Line>
     */
    public function all(): array;

    public function exists(string $id): bool;
}
