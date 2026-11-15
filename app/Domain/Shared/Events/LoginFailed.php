<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class LoginFailed
{
    public function __construct(
        public string $email,
        /** Id do usuário, quando o e-mail pertence a uma conta. */
        public ?string $userId,
    ) {}
}
