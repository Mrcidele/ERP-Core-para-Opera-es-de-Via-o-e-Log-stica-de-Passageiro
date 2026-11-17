<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Enums\UserRole;
use SensitiveParameter;

final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public UserRole $role,
        /** Senha em texto puro: só vive até virar hash; nunca vai para log, auditoria ou resposta. */
        #[SensitiveParameter]
        public string $password,
    ) {}
}
