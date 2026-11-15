<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use SensitiveParameter;

final readonly class LoginDTO
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
