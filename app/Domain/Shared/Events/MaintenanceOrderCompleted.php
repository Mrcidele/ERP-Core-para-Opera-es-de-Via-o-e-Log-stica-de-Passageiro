<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class MaintenanceOrderCompleted
{
    public function __construct(
        public string $orderId,
        public string $vehicleId,
        public int $costCents,
    ) {}
}
