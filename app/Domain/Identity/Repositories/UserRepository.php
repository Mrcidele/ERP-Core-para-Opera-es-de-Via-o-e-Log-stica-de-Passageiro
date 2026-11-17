<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;

interface UserRepository
{
    public function nextIdentity(): string;

    /**
     * @throws EmailAlreadyTakenException quando outro cadastro simultâneo levou o mesmo e-mail
     */
    public function add(User $user): void;

    public function findById(string $id): ?User;

    /** A busca não diferencia maiúsculas de minúsculas. */
    public function findByEmail(string $email): ?User;
}
