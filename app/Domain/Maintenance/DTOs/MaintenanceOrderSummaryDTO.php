<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\DTOs;

use App\Domain\Maintenance\Entities\MaintenanceOrder;

/** Ordem de serviço com os dados de veículo e mecânico que a listagem exibe. */
final readonly class MaintenanceOrderSummaryDTO
{
    public function __construct(
        public MaintenanceOrder $order,
        public string $vehiclePlate,
        public string $mechanicName,
    ) {}
}
