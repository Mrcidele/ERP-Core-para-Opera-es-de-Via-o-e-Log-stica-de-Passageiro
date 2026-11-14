<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;

final class InMemoryMaintenanceOrderRepository implements MaintenanceOrderRepository
{
    /** @var array<string, MaintenanceOrder> */
    public array $orders = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function allWithDetails(): array
    {
        $orders = array_values($this->orders);

        usort($orders, fn (MaintenanceOrder $a, MaintenanceOrder $b): int => $b->openedAt <=> $a->openedAt);

        return array_map(
            fn (MaintenanceOrder $order): MaintenanceOrderSummaryDTO => new MaintenanceOrderSummaryDTO($order, 'AAA0A00', 'Mecânico'),
            $orders,
        );
    }

    public function findById(string $id): ?MaintenanceOrder
    {
        return $this->orders[$id] ?? null;
    }

    public function hasActiveOrders(string $vehicleId): bool
    {
        return array_any(
            $this->orders,
            fn (MaintenanceOrder $order): bool => $order->vehicleId === $vehicleId && $order->status->isActive(),
        );
    }

    public function save(MaintenanceOrder $order): void
    {
        $this->orders[$order->id] = $order;
    }
}
