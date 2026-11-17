<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Identity\Contracts\PasswordHasher;
use SensitiveParameter;

/** "Hash" legível para os testes: a senha `abc` vira `hashed:abc`; `legacy:abc` é um hash antigo, aceito e a refazer. */
final class FakePasswordHasher implements PasswordHasher
{
    /** @var list<string> hashes contra os quais alguma senha foi comparada */
    public array $comparedAgainst = [];

    public function verify(#[SensitiveParameter] string $plainPassword, string $hash): bool
    {
        $this->comparedAgainst[] = $hash;

        return $hash === 'hashed:'.$plainPassword || $hash === 'legacy:'.$plainPassword;
    }

    public function hash(#[SensitiveParameter] string $plainPassword): string
    {
        return 'hashed:'.$plainPassword;
    }

    public function needsRehash(string $hash): bool
    {
        return str_starts_with($hash, 'legacy:');
    }
}
