<?php

declare(strict_types=1);

namespace App\Domain\Operations\Entities;

final readonly class Line
{
    public function __construct(
        public string $id,
        public string $origin,
        public string $destination,
        public int $distanceKm,
        public int $estimatedDurationMinutes,
    ) {}
}
