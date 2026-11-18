<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class LoginSucceeded
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}
