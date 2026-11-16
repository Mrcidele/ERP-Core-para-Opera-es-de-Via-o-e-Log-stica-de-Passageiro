<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class TimelineDriverDTO
{
    /**
     * @param  list<TimelineTripDTO>  $trips
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $trips,
    ) {}
}
