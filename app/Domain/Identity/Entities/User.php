<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\UserRole;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public UserRole $role,
        /** Hash da senha; nunca sai em Resource. */
        public string $passwordHash,
    ) {}
}
