<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\User;

interface UserRepository
{
    public function findById(string $id): ?User;

    /** A busca não diferencia maiúsculas de minúsculas. */
    public function findByEmail(string $email): ?User;
}
