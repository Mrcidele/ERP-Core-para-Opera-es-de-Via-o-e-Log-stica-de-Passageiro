<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Contracts\TokenService;

final readonly class LogoutAction
{
    public function __construct(
        private TokenService $tokens,
    ) {}

    public function execute(string $plainTextToken): void
    {
        $this->tokens->revoke($plainTextToken);
    }
}
