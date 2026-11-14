<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\DTOs;

final readonly class CompleteMaintenanceOrderDTO
{
    public function __construct(
        public string $orderId,
        public int $costCents,
    ) {}
}
