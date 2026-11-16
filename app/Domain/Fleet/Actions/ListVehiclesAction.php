<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListVehiclesAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    /**
     * @param  bool  $onlyDueForMaintenance  só veículos com a revisão preventiva vencida
     * @return Page<Vehicle>
     */
    public function execute(PageQuery $query = new PageQuery, bool $onlyDueForMaintenance = false): Page
    {
        return $this->vehicles->paginate($query, $onlyDueForMaintenance);
    }
}
