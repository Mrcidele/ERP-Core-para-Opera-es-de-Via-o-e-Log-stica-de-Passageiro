<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Repositories\LineRepository;
use App\Infrastructure\Database\Models\LineModel;
use Illuminate\Support\Str;

final class EloquentLineRepository implements LineRepository
{
    public function all(): array
    {
        return array_values(
            LineModel::query()
                ->orderBy('origin')
                ->orderBy('destination')
                ->get()
                ->map(fn (LineModel $model): Line => $this->toEntity($model))
                ->all(),
        );
    }

    public function exists(string $id): bool
    {
        // Um id fora do formato UUID quebraria a consulta no PostgreSQL.
        return Str::isUuid($id) && LineModel::query()->whereKey($id)->exists();
    }

    public function findById(string $id): ?Line
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = LineModel::withTrashed()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    private function toEntity(LineModel $model): Line
    {
        return new Line(
            id: $model->id,
            origin: $model->origin,
            destination: $model->destination,
            distanceKm: $model->distance_km,
            estimatedDurationMinutes: $model->estimated_duration_minutes,
        );
    }
}
