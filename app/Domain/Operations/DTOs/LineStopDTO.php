<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class LineStopDTO
{
    public function __construct(
        public string $cityName,
        public int $cumulativeKm,
        public int $estimatedMinutes,
    ) {}
}
