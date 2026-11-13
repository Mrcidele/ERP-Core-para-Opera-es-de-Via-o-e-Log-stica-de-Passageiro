<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use App\Domain\Shared\Contracts\Clock;
use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
