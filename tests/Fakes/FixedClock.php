<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Shared\Contracts\Clock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class FixedClock implements Clock
{
    /** @param  string  $now  Data ou data e hora em UTC, ex.: '2026-11-13' ou '2026-11-13 10:00:00'. */
    public function __construct(private string $now) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->now, new DateTimeZone('UTC'));
    }

    public function today(): DateTimeImmutable
    {
        return $this->now()->setTime(0, 0);
    }
}
