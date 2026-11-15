<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class LineCreated
{
    public function __construct(
        public string $lineId,
        /** @var array<string, int|string> */
        public array $line,
    ) {}
}
