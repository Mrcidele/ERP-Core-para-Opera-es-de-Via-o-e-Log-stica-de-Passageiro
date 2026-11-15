<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Entities\MaintenanceOrder;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Repositories\MaintenanceOrderRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\MaintenanceOrderModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentMaintenanceOrderRepository implements MaintenanceOrderRepository
{
    use PaginatesQueries;

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function paginateWithDetails(PageQuery $query): Page
    {
        return $this->paginateQuery($this->filtered($query), $query, $this->toSummary(...));
    }

    public function streamWithDetails(PageQuery $filters): iterable
    {
        // lazy() busca em blocos: a exportação não carrega todas as ordens de uma vez.
        foreach ($this->filtered($filters)->lazy(500) as $model) {
            yield $this->toSummary($model);
        }
    }

    /**
     * @return Builder<MaintenanceOrderModel>
     */
    private function filtered(PageQuery $query): Builder
    {
        return MaintenanceOrderModel::query()
            ->with(['vehicle', 'mechanic'])
            ->when($query->status !== null, fn (Builder $orders) => $orders->where('status', $query->status))
            ->when($query->search !== null, function (Builder $orders) use ($query): void {
                $term = $this->likeTerm((string) $query->search);

                $orders->where(fn (Builder $match) => $match
                    ->where('description', 'ilike', $term)
                    ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('plate', 'ilike', $term))
                    ->orWhereHas('mechanic', fn (Builder $mechanic) => $mechanic->where('name', 'ilike', $term)));
            })
            ->orderByDesc('opened_at')
            ->orderBy('id');
    }

    private function toSummary(MaintenanceOrderModel $model): MaintenanceOrderSummaryDTO
    {
        return new MaintenanceOrderSummaryDTO(
            order: $this->toEntity($model),
            vehiclePlate: $model->vehicle->plate ?? '',
            mechanicName: $model->mechanic->name ?? '',
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
