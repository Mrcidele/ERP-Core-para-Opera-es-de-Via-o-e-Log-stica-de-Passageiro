<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

use DateTimeImmutable;

/** Painel operacional. Um bloco é null quando o usuário não tem permissão para vê-lo. */
final readonly class DashboardSummaryDTO
{
    public function __construct(
        public DateTimeImmutable $generatedAt,
        public ?FleetSummaryDTO $fleet,
        public ?OperationsSummaryDTO $operations,
        public ?CrewAlertsDTO $crew,
        public ?MaintenanceSummaryDTO $maintenance,
    ) {}
}
