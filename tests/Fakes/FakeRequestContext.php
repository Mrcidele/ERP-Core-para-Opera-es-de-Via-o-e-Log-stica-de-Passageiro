<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Audit\Contracts\RequestContext;

final readonly class FakeRequestContext implements RequestContext
{
    public function __construct(
        private ?string $userId = 'user-1',
        private ?string $ipAddress = '203.0.113.7',
    ) {}

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }
}
