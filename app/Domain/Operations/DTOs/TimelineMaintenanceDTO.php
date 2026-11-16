<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use DateTimeImmutable;

/** Ordem de serviço pendente que tira o veículo da escala; sem previsão de término. */
final readonly class TimelineMaintenanceDTO
{
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public DateTimeImmutable $openedAt,
    ) {}
}
