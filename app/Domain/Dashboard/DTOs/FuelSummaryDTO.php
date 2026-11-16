<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

use DateTimeImmutable;

/** Combustível abastecido desde o início do mês corrente. */
final readonly class FuelSummaryDTO
{
    public function __construct(
        public DateTimeImmutable $since,
        public int $costCents,
        public int $litersMilliliters,
        public int $logCount,
    ) {}
}
