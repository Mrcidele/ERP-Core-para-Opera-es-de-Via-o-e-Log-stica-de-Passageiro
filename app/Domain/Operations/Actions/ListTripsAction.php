<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Repositories\TripRepository;

final readonly class ListTripsAction
{
    public function __construct(
        private TripRepository $trips,
    ) {}

    /**
     * @return list<TripSummaryDTO>
     */
    public function execute(): array
    {
        return $this->trips->allWithDetails();
    }
}
