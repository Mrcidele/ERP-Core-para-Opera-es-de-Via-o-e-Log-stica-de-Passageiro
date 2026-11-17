<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use SensitiveParameter;

interface PasswordHasher
{
    public function verify(#[SensitiveParameter] string $plainPassword, string $hash): bool;

    public function hash(#[SensitiveParameter] string $plainPassword): string;
}
