<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentVehicleRepository implements VehicleRepository
{
    use PaginatesQueries;

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function paginate(PageQuery $query, bool $onlyDueForMaintenance = false): Page
    {
        $builder = VehicleModel::query()
            // Mesma regra de Vehicle::needsPreventiveMaintenance().
            ->when($onlyDueForMaintenance, fn (Builder $vehicles) => $vehicles
                ->whereNotNull('next_preventive_maintenance_km')
                ->whereColumn('current_mileage', '>=', 'next_preventive_maintenance_km'))
            ->when($query->status !== null, fn (Builder $vehicles) => $vehicles->where('status', $query->status))
            ->when($query->search !== null, function (Builder $vehicles) use ($query): void {
                // Placa e chassi são gravados em maiúsculas, sem hífen nem espaço.
                $term = $this->likeTerm((string) preg_replace('/[\s\-]/', '', (string) $query->search));

                $vehicles->where(fn (Builder $match) => $match
                    ->where('plate', 'ilike', $term)
                    ->orWhere('chassis', 'ilike', $term)
                    ->orWhere('model', 'ilike', $this->likeTerm((string) $query->search)));
            })
            ->orderBy('plate')
            ->orderBy('id');

        return $this->paginateQuery($builder, $query, fn (VehicleModel $model): Vehicle => $this->toEntity($model));
    }

    public function findById(string $id): ?Vehicle
    {
        // Um id fora do formato UUID quebraria a consulta no PostgreSQL.
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = VehicleModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function existsByPlate(string $plate, ?string $exceptId = null): bool
    {
        return VehicleModel::query()
            ->where('plate', $plate)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function existsByChassis(string $chassis, ?string $exceptId = null): bool
    {
        return VehicleModel::query()
            ->where('chassis', $chassis)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function save(Vehicle $vehicle): void
    {
        VehicleModel::query()->updateOrCreate(
            ['id' => $vehicle->id],
            [
                'plate' => $vehicle->plate,
                'chassis' => $vehicle->chassis,
                'payload_kg' => $vehicle->payloadKg,
                'vehicle_type' => $vehicle->type,
                'status' => $vehicle->status,
                'current_mileage' => $vehicle->currentMileage,
                'next_preventive_maintenance_km' => $vehicle->nextPreventiveMaintenanceKm,
                'min_meters_per_liter' => $vehicle->minMetersPerLiter,
                'model' => $vehicle->model,
            ],
        );
    }

    /** Exclusão lógica: o registro fica no banco para preservar o histórico. */
    public function delete(string $id): void
    {
        VehicleModel::query()->whereKey($id)->delete();
    }

    private function toEntity(VehicleModel $model): Vehicle
    {
        return new Vehicle(
            id: $model->id,
            plate: $model->plate,
            chassis: $model->chassis,
            payloadKg: $model->payload_kg,
            type: $model->vehicle_type,
            status: $model->status,
            currentMileage: $model->current_mileage,
            nextPreventiveMaintenanceKm: $model->next_preventive_maintenance_km,
            minMetersPerLiter: $model->min_meters_per_liter,
            model: $model->model,
        );
    }
}
