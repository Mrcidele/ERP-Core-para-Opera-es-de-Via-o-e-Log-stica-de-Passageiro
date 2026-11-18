<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\Enums\UserRole;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public UserRole $role,
        /** Hash da senha; nunca sai em Resource. */
        public string $passwordHash,
        /** Quando o acesso foi desativado; nulo enquanto o usuário pode entrar. */
        public ?DateTimeImmutable $deactivatedAt = null,
    ) {}

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function isActiveAdministrator(): bool
    {
        return $this->isActive() && $this->role === UserRole::Admin;
    }
}
