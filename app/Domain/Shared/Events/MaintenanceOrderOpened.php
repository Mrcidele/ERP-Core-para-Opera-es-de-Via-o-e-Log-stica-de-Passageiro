<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class MaintenanceOrderOpened
{
    public function __construct(
        public string $orderId,
        public string $vehicleId,
        public string $mechanicId,
        public string $type,
    ) {}
}
