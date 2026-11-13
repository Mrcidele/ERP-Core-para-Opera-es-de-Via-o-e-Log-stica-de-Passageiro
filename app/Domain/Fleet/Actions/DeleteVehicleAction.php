<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class DeleteVehicleAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    public function execute(string $id): void
    {
        if ($this->vehicles->findById($id) === null) {
            throw new VehicleNotFoundException($id);
        }

        $this->vehicles->delete($id);
    }
}
