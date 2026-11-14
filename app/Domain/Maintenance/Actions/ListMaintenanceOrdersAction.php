<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;

final readonly class ListMaintenanceOrdersAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
    ) {}

    /**
     * @return list<MaintenanceOrderSummaryDTO>
     */
    public function execute(): array
    {
        return $this->orders->allWithDetails();
    }
}
