<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListMaintenanceOrdersAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
    ) {}

    /**
     * @return Page<MaintenanceOrderSummaryDTO>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->orders->paginateWithDetails($query);
    }
}
