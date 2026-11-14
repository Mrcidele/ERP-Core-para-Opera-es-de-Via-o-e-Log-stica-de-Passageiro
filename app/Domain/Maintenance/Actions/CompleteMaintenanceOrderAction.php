<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\Contracts\FleetGateway;
use App\Domain\Maintenance\DTOs\CompleteMaintenanceOrderDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Exceptions\MaintenanceOrderNotFoundException;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Shared\Contracts\Clock;

final readonly class CompleteMaintenanceOrderAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
        private FleetGateway $fleet,
        private Clock $clock,
    ) {}

    public function execute(CompleteMaintenanceOrderDTO $data): MaintenanceOrder
    {
        $order = ($this->orders->findById($data->orderId) ?? throw new MaintenanceOrderNotFoundException($data->orderId))
            ->complete($data->costCents, $this->clock->now());

        $this->orders->save($order);

        // O veículo só volta a rodar quando a última ordem pendente dele é encerrada.
        if (! $this->orders->hasActiveOrders($order->vehicleId)) {
            $this->fleet->markVehicleActive($order->vehicleId);
        }

        return $order;
    }
}
