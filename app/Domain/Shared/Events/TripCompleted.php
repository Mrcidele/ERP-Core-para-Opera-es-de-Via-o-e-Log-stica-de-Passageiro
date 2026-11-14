<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/** Uma viagem foi concluída. Fica no núcleo compartilhado porque outros módulos reagem a ele. */
final readonly class TripCompleted
{
    public function __construct(
        public string $tripId,
        public string $vehicleId,
        public int $distanceKm,
    ) {}
}
