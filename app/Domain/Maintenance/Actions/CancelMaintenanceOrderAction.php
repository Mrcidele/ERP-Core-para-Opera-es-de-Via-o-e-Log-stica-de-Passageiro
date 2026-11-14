<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\Contracts\FleetGateway;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Exceptions\MaintenanceOrderNotFoundException;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;

final readonly class CancelMaintenanceOrderAction
{
    public function __construct(
        private MaintenanceOrderRepository $orders,
        private FleetGateway $fleet,
    ) {}

    public function execute(string $orderId): MaintenanceOrder
    {
        $order = ($this->orders->findById($orderId) ?? throw new MaintenanceOrderNotFoundException($orderId))->cancel();

        $this->orders->save($order);

        // Mesma regra da conclusão: sem ordens pendentes, o veículo volta a rodar.
        if (! $this->orders->hasActiveOrders($order->vehicleId)) {
            $this->fleet->markVehicleActive($order->vehicleId);
        }

        return $order;
    }
}
