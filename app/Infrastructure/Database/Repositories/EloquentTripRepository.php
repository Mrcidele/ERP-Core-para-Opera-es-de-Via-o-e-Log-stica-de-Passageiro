<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\DriverRestViolationException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use App\Domain\Operations\Repositories\TripRepository;
use App\Infrastructure\Database\Models\TripModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentTripRepository implements TripRepository
{
    /** SQLSTATE do PostgreSQL para violação de constraint EXCLUDE. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function allWithDetails(): array
    {
        return array_values(
            TripModel::query()
                ->with(['line', 'vehicle', 'driver'])
                ->orderBy('departure_time')
                ->orderBy('id')
                ->get()
                ->map(fn (TripModel $model): TripSummaryDTO => new TripSummaryDTO(
                    trip: $this->toEntity($model),
                    lineOrigin: $model->line->origin ?? '',
                    lineDestination: $model->line->destination ?? '',
                    lineDistanceKm: $model->line->distance_km ?? 0,
                    vehiclePlate: $model->vehicle->plate ?? '',
                    driverName: $model->driver->name ?? '',
                ))
                ->all(),
        );
    }

    public function findById(string $id): ?Trip
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = TripModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function hasVehicleConflict(string $vehicleId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->hasConflict('vehicle_id', $vehicleId, $from, $to);
    }

    public function hasDriverConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $this->hasConflict('driver_id', $driverId, $from, $to);
    }

    public function hasDriverRestConflict(string $driverId, DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): bool
    {
        // Mesmo teste de sobreposição, com o período alargado pelo descanso dos dois lados.
        return $this->hasConflict('driver_id', $driverId, $from->modify("-{$restHours} hours"), $to->modify("+{$restHours} hours"));
    }

    public function save(Trip $trip): void
    {
        try {
            // Transação própria (savepoint, se já houver uma aberta): se a constraint
            // recusar a gravação, a transação de quem chamou continua utilizável.
            DB::transaction(fn () => TripModel::query()->updateOrCreate(
                ['id' => $trip->id],
                [
                    'line_id' => $trip->lineId,
                    'vehicle_id' => $trip->vehicleId,
                    'driver_id' => $trip->driverId,
                    'departure_time' => $this->toUtc($trip->departureTime),
                    'arrival_time' => $this->toUtc($trip->arrivalTime),
                    'status' => $trip->status,
                ],
            ));
        } catch (QueryException $e) {
            if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
                throw $e;
            }

            // Outra requisição gravou no mesmo período depois da checagem da Action.
            if (! str_contains($e->getMessage(), 'trips_driver_rest')) {
                throw new VehicleScheduleConflictException;
            }

            // A constraint do motorista cobre sobreposição e descanso: distingue os dois para a mensagem.
            throw $this->hasDriverConflict($trip->driverId, $trip->departureTime, $trip->arrivalTime)
                ? new DriverScheduleConflictException
                : new DriverRestViolationException;
        }
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

    private function toEntity(TripModel $model): Trip
    {
        return new Trip(
            id: $model->id,
            lineId: $model->line_id,
            vehicleId: $model->vehicle_id,
            driverId: $model->driver_id,
            departureTime: $model->departure_time->toDateTimeImmutable(),
            arrivalTime: $model->arrival_time->toDateTimeImmutable(),
            status: $model->status,
        );
    }

    private function toUtc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');
    }
}
