<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Repositories\TripRepository;
use App\Infrastructure\Database\Models\TripModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

final class EloquentTripRepository implements TripRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function hasVehicleConflict(string $vehicleId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->hasConflict('vehicle_id', $vehicleId, $from, $to);
    }

    public function hasDriverConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->hasConflict('driver_id', $driverId, $from, $to);
    }

    public function save(Trip $trip): void
    {
        TripModel::query()->updateOrCreate(
            ['id' => $trip->id],
            [
                'line_id' => $trip->lineId,
                'vehicle_id' => $trip->vehicleId,
                'driver_id' => $trip->driverId,
                'departure_time' => $this->toUtc($trip->departureTime),
                'arrival_time' => $this->toUtc($trip->arrivalTime),
                'status' => $trip->status,
            ],
        );
    }

    /**
     * Dois períodos se cruzam quando cada um começa antes de o outro terminar.
     *
     * @param  'vehicle_id'|'driver_id'  $column
     */
    private function hasConflict(string $column, string $id, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return TripModel::query()
            ->where($column, $id)
            ->where('status', '!=', TripStatus::Canceled->value)
            ->where('departure_time', '<', $this->toUtc($to))
            ->where('arrival_time', '>', $this->toUtc($from))
            ->exists();
    }

    private function toUtc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
