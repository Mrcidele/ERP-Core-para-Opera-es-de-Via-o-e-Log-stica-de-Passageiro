<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\Contracts\CrewGateway;
use App\Domain\Maintenance\Contracts\FleetGateway;
use App\Domain\Maintenance\DTOs\OpenMaintenanceOrderDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Exceptions\MechanicNotAvailableException;
use App\Domain\Maintenance\Exceptions\VehicleNotFoundException;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\MaintenanceOrderOpened;

final readonly class OpenMaintenanceOrderAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
        private FleetGateway $fleet,
        private CrewGateway $crew,
        private Clock $clock,
        private EventDispatcher $events,
    ) {}

    public function execute(OpenMaintenanceOrderDTO $data): MaintenanceOrder
    {
        if (! $this->fleet->vehicleExists($data->vehicleId)) {
            throw new VehicleNotFoundException;
        }

        if (! $this->crew->isActiveMechanic($data->mechanicId)) {
            throw new MechanicNotAvailableException;
        }

        $order = MaintenanceOrder::open($this->orders->nextIdentity(), $data, $this->clock->now());

        $this->orders->save($order);
        $this->fleet->markVehicleInMaintenance($order->vehicleId);

        $this->events->dispatch(new MaintenanceOrderOpened($order->id, $order->vehicleId, $order->mechanicId, $order->type->value));

        return $order;
    }
}
