<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Shared\Pagination\PageQuery;

/** Entrega todas as ordens de serviço que atendem aos filtros, uma a uma, para gerar o arquivo de exportação. */
final readonly class ExportMaintenanceOrdersAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
    ) {}

    /**
     * @return iterable<MaintenanceOrderSummaryDTO>
     */
    public function execute(PageQuery $filters = new PageQuery): iterable
    {
        return $this->orders->streamWithDetails($filters);
    }
}
