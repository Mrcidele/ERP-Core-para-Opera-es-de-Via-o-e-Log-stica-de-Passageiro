<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use App\Domain\Shared\Contracts\Clock;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/** Usa o relógio do Carbon, que os testes de feature conseguem congelar com travelTo(). */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return CarbonImmutable::now('UTC')->toDateTimeImmutable();
    }

    public function today(): DateTimeImmutable
    {
        return CarbonImmutable::today('UTC')->toDateTimeImmutable();
    }
}
