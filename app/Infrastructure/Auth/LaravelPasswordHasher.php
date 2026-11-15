<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Identity\Contracts\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private Hasher $hasher) {}

    public function verify(#[SensitiveParameter] string $plainPassword, string $hash): bool
    {
        return $this->hasher->check($plainPassword, $hash);
    }
}
