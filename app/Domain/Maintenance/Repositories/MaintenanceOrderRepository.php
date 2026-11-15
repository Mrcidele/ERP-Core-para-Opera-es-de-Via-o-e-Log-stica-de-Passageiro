<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Repositories;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

interface MaintenanceOrderRepository
{
    public function nextIdentity(): string;

    /**
     * Ordens da mais recente para a mais antiga, com veículo e mecânico.
     * Busca por placa, mecânico ou descrição; filtro por status.
     *
     * @return Page<MaintenanceOrderSummaryDTO>
     */
    public function paginateWithDetails(PageQuery $query): Page;

    /**
     * Todas as ordens que atendem aos filtros (a página é ignorada), lidas aos poucos para exportação.
     *
     * @return iterable<MaintenanceOrderSummaryDTO>
     */
    public function streamWithDetails(PageQuery $filters): iterable;

    public function findById(string $id): ?MaintenanceOrder;

    /** O veículo tem ordem aberta ou em andamento? */
    public function hasActiveOrders(string $vehicleId): bool;

    public function save(MaintenanceOrder $order): void;
}
