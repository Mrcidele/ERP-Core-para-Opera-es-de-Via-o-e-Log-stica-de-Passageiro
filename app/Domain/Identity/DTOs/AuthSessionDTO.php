<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Entities\User;

/** Resultado de um login: o usuário e o token de acesso em texto puro, exibido uma única vez. */
final readonly class AuthSessionDTO
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
