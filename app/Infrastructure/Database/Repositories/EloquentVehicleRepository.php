<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;
use App\Infrastructure\Database\Models\VehicleModel;
use Illuminate\Support\Str;

final class EloquentVehicleRepository implements VehicleRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function all(): array
    {
        return array_values(
            VehicleModel::query()
                ->orderBy('plate')
                ->get()
                ->map(fn (VehicleModel $model): Vehicle => $this->toEntity($model))
                ->all(),
        );
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
                'capacity' => $vehicle->capacity,
                'class_type' => $vehicle->classType,
                'status' => $vehicle->status,
                'current_mileage' => $vehicle->currentMileage,
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
            capacity: $model->capacity,
            classType: $model->class_type,
            status: $model->status,
            currentMileage: $model->current_mileage,
        );
    }
}
