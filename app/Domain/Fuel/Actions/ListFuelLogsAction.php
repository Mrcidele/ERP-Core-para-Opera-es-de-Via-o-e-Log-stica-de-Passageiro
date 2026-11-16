<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Actions;

use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use App\Domain\Fuel\Repositories\FuelLogRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListFuelLogsAction
{
    public function __construct(
        private FuelLogRepository $logs,
    ) {}

    /**
     * @return Page<FuelLogSummaryDTO>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->logs->paginateWithDetails($query);
    }
}
