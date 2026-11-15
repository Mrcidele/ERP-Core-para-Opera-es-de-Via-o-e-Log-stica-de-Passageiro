<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Ordens concluídas no período (quantidade e custo) e ordens ainda pendentes. */
final readonly class MaintenanceSummaryDTO
{
    public function __construct(
        public int $completedCount,
        public int $completedCostCents,
        public int $openCount,
        public int $inProgressCount,
    ) {}
}
