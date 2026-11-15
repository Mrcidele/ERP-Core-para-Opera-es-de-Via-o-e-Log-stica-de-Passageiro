<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

/** Dados de cadastro de uma linha, usados na criação e na edição. */
final readonly class LineDataDTO
{
    /**
     * @param  list<LineStopDTO>  $stops  na ordem de passagem
     */
    public function __construct(
        public string $origin,
        public string $destination,
        public int $distanceKm,
        public int $estimatedDurationMinutes,
        public array $stops = [],
    ) {}
}
