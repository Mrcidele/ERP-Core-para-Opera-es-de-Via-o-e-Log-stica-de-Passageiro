<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListTripsAction
{
    public function __construct(
        private TripRepository $trips,
    ) {}

    /**
     * @return Page<TripSummaryDTO>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->trips->paginateWithDetails($query);
    }
}
