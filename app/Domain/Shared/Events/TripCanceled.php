<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class TripCanceled
{
    public function __construct(
        public string $tripId,
    ) {}
}
