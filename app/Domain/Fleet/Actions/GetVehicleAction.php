<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class GetVehicleAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    public function execute(string $id): Vehicle
    {
        return $this->vehicles->findById($id) ?? throw new VehicleNotFoundException($id);
    }
}
