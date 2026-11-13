<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Shared\Contracts\Clock;
use DateTimeImmutable;

final readonly class FixedClock implements Clock
{
    public function __construct(private string $today) {}

    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->today);
    }
}
