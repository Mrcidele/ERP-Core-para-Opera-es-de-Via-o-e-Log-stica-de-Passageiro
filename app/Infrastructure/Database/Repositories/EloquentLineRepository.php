<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Entities\LineStop;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\LineHasActiveTripsException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Infrastructure\Database\Models\LineModel;
use App\Infrastructure\Database\Models\LineStopModel;
use App\Infrastructure\Database\Models\TripModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentLineRepository implements LineRepository
{
    private const ACTIVE_TRIP_STATUSES = [TripStatus::Scheduled->value, TripStatus::InProgress->value];

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function all(): array
    {
        return array_values(
            LineModel::query()
                ->with('stops')
                ->orderBy('origin')
                ->orderBy('destination')
                ->orderBy('id')
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

        $model = LineModel::withTrashed()->with('stops')->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findActive(string $id): ?Line
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = LineModel::query()->with('stops')->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function hasActiveTrips(string $id): bool
    {
        return Str::isUuid($id)
            && TripModel::query()->where('line_id', $id)->whereIn('status', self::ACTIVE_TRIP_STATUSES)->exists();
    }

    public function save(Line $line): void
    {
        // Linha e paradas entram juntas: uma falha em qualquer parada desfaz tudo.
        DB::transaction(function () use ($line): void {
            LineModel::query()->updateOrCreate(['id' => $line->id], [
                'origin' => $line->origin,
                'destination' => $line->destination,
                'distance_km' => $line->distanceKm,
                'estimated_duration_minutes' => $line->estimatedDurationMinutes,
            ]);

            LineStopModel::query()->where('line_id', $line->id)->delete();

            foreach ($line->stops as $stop) {
                LineStopModel::query()->create([
                    'line_id' => $line->id,
                    'sequence' => $stop->sequence,
                    'city_name' => $stop->cityName,
                    'cumulative_km' => $stop->cumulativeKm,
                    'estimated_minutes' => $stop->estimatedMinutes,
                ]);
            }
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id): void {
            // Um agendamento simultâneo nesta linha segura a linha pela FK até confirmar; com a trava,
            // a exclusão espera por ele e a conferência abaixo já enxerga a viagem nova.
            $line = LineModel::query()->whereKey($id)->lockForUpdate()->first();

            if ($line === null) {
                return;
            }

            if ($this->hasActiveTrips($id)) {
                throw new LineHasActiveTripsException;
            }

            $line->delete();
        });
    }

    private function toEntity(LineModel $model): Line
    {
        return new Line(
            id: $model->id,
            origin: $model->origin,
            destination: $model->destination,
            distanceKm: $model->distance_km,
            estimatedDurationMinutes: $model->estimated_duration_minutes,
            stops: array_values($model->stops->map(fn (LineStopModel $stop): LineStop => new LineStop(
                sequence: $stop->sequence,
                cityName: $stop->city_name,
                cumulativeKm: $stop->cumulative_km,
                estimatedMinutes: $stop->estimated_minutes,
            ))->all()),
        );
    }
}
