<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

final class EloquentMaintenanceOrderRepository implements MaintenanceOrderRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function allWithDetails(): array
    {
        return array_values(
            MaintenanceOrderModel::query()
                ->with(['vehicle', 'mechanic'])
                ->orderByDesc('opened_at')
                ->orderBy('id')
                ->get()
                ->map(fn (MaintenanceOrderModel $model): MaintenanceOrderSummaryDTO => new MaintenanceOrderSummaryDTO(
                    order: $this->toEntity($model),
                    vehiclePlate: $model->vehicle->plate ?? '',
                    mechanicName: $model->mechanic->name ?? '',
                ))
                ->all(),
        );
    }

    public function findById(string $id): ?MaintenanceOrder
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = MaintenanceOrderModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function hasActiveOrders(string $vehicleId): bool
    {
        return MaintenanceOrderModel::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', [MaintenanceStatus::Open->value, MaintenanceStatus::InProgress->value])
            ->exists();
    }

    public function save(MaintenanceOrder $order): void
    {
        MaintenanceOrderModel::query()->updateOrCreate(
            ['id' => $order->id],
            [
                'vehicle_id' => $order->vehicleId,
                'mechanic_id' => $order->mechanicId,
                'type' => $order->type,
                'status' => $order->status,
                'description' => $order->description,
                'cost_cents' => $order->costCents,
                'opened_at' => $this->toUtc($order->openedAt),
                'completed_at' => $order->completedAt === null ? null : $this->toUtc($order->completedAt),
            ],
        );
    }

    private function toEntity(MaintenanceOrderModel $model): MaintenanceOrder
    {
        return new MaintenanceOrder(
            id: $model->id,
            vehicleId: $model->vehicle_id,
            mechanicId: $model->mechanic_id,
            type: $model->type,
            status: $model->status,
            description: $model->description,
            costCents: $model->cost_cents,
            openedAt: $model->opened_at->toDateTimeImmutable(),
            completedAt: $model->completed_at?->toDateTimeImmutable(),
        );
    }

    private function toUtc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }
}
