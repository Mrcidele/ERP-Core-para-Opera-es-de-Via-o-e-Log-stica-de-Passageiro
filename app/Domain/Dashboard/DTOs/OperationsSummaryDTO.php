<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Viagens em andamento, agendadas para as próximas 24 horas e as próximas da fila. */
final readonly class OperationsSummaryDTO
{
    public function __construct(
        public int $inProgressNow,
        public int $scheduledNext24Hours,
        /** @var list<UpcomingTripDTO> */
        public array $nextTrips,
    ) {}
}
