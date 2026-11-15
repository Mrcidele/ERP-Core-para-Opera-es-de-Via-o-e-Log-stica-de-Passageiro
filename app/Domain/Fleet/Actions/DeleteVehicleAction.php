<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\VehicleDeleted;

final readonly class DeleteVehicleAction
{
    public function __construct(
        private VehicleRepository $vehicles,
        private EventDispatcher $events,
    ) {}

    public function execute(string $id): void
    {
        $vehicle = $this->vehicles->findById($id) ?? throw new VehicleNotFoundException($id);

        $this->vehicles->delete($id);

        $this->events->dispatch(new VehicleDeleted($vehicle->id, $vehicle->plate, $vehicle->chassis, $vehicle->status->value));
    }
}
