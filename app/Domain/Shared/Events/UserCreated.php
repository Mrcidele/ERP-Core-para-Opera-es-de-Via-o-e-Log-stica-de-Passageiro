<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class UserCreated
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public string $role,
        /** Por onde o usuário foi criado (ex.: `console`). */
        public string $channel,
    ) {}
}
