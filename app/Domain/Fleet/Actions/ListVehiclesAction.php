<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class ListVehiclesAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    /**
     * @return list<Vehicle>
     */
    public function execute(): array
    {
        return $this->vehicles->all();
    }
}
