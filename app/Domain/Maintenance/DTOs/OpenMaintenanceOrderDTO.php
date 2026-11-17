<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\DTOs;

use App\Domain\Maintenance\Enums\MaintenanceType;
use DateTimeImmutable;

final readonly class OpenMaintenanceOrderDTO
{
    public function __construct(
        public string $vehicleId,
        public string $mechanicId,
        public MaintenanceType $type,
        public string $description,
        /** Previsão de o veículo sair da oficina; opcional. */
        public ?DateTimeImmutable $estimatedCompletionAt = null,
    ) {}
}
