<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Pagination\PageQuery;

/** Entrega todas as viagens que atendem aos filtros, uma a uma, para gerar o arquivo de exportação. */
final readonly class ExportTripsAction
{
    public function __construct(
        private TripRepository $trips,
    ) {}

    /**
     * @return iterable<TripSummaryDTO>
     */
    public function execute(PageQuery $filters = new PageQuery): iterable
    {
        return $this->trips->streamWithDetails($filters);
    }
}
