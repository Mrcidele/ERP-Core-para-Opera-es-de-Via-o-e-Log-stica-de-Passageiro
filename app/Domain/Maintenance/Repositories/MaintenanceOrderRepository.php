<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Repositories;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;

interface MaintenanceOrderRepository
{
    public function nextIdentity(): string;

    /**
     * Ordens da mais recente para a mais antiga, com veículo e mecânico.
     *
     * @return list<MaintenanceOrderSummaryDTO>
     */
    public function allWithDetails(): array;

    public function findById(string $id): ?MaintenanceOrder;

    /** O veículo tem ordem aberta ou em andamento? */
    public function hasActiveOrders(string $vehicleId): bool;

    public function save(MaintenanceOrder $order): void;
}
