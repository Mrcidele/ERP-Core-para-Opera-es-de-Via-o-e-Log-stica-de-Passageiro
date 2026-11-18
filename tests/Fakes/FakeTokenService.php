<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Identity\Contracts\TokenService;

final class FakeTokenService implements TokenService
{
    /** @var array<string, string> token => id do usuário */
    public array $active = [];

    private int $sequence = 0;

    public function issueFor(string $userId): string
    {
        $this->active = array_filter($this->active, fn (string $owner): bool => $owner !== $userId);

        $token = 'token-'.++$this->sequence;
        $this->active[$token] = $userId;

        return $token;
    }

    public function revoke(string $plainTextToken): void
    {
        unset($this->active[$plainTextToken]);
    }

    public function revokeAllFor(string $userId): void
    {
        $this->active = array_filter($this->active, fn (string $owner): bool => $owner !== $userId);
    }
}
