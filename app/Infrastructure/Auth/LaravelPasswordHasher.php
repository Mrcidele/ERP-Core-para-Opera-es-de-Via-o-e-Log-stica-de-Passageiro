<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Identity\Contracts\PasswordHasher;
use Illuminate\Hashing\HashManager;
use SensitiveParameter;

/**
 * Senhas novas usam o driver configurado (Argon2id). Contas criadas antes, com bcrypt, continuam
 * entrando: o hash antigo é conferido pelo driver bcrypt e refeito em Argon2id no login.
 */
final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private HashManager $hasher) {}

    public function verify(#[SensitiveParameter] string $plainPassword, string $hash): bool
    {
        $usesBcrypt = $this->hasher->info($hash)['algoName'] === 'bcrypt';

        return $usesBcrypt && $this->hasher->getDefaultDriver() !== 'bcrypt'
            ? $this->hasher->createBcryptDriver()->check($plainPassword, $hash)
            : $this->hasher->check($plainPassword, $hash);
    }

    public function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return $this->hasher->make($plainPassword);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->hasher->needsRehash($hash);
    }
}
