<?php

declare(strict_types=1);

namespace App\Domain\Operations\Entities;

use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Enums\TripStatus;
use App\Domain\Operations\Exceptions\InvalidTripTransitionException;
use DateTimeImmutable;

final readonly class Trip
{
    /** Descanso mínimo do motorista entre duas viagens (interjornada da Lei do Motorista, Lei 13.103/2015). */
    public const DRIVER_REST_HOURS = 11;

    public function __construct(
        public string $id,
        public string $lineId,
        public string $vehicleId,
        public string $driverId,
        public DateTimeImmutable $departureTime,
        public DateTimeImmutable $arrivalTime,
        public TripStatus $status,
    ) {}

    public static function schedule(string $id, ScheduleTripDTO $data): self
    {
        return new self(
            id: $id,
            lineId: $data->lineId,
            vehicleId: $data->vehicleId,
            driverId: $data->driverId,
            departureTime: $data->departureTime,
            arrivalTime: $data->arrivalTime,
            status: TripStatus::Scheduled,
        );
    }

    public function start(): self
    {
        if ($this->status !== TripStatus::Scheduled) {
            throw new InvalidTripTransitionException('Só é possível iniciar uma viagem agendada.');
        }

        return $this->withStatus(TripStatus::InProgress);
    }

    public function complete(): self
    {
        if ($this->status !== TripStatus::InProgress) {
            throw new InvalidTripTransitionException('Só é possível concluir uma viagem em andamento.');
        }

        return $this->withStatus(TripStatus::Completed);
    }

    public function cancel(): self
    {
        if ($this->status !== TripStatus::Scheduled) {
            throw new InvalidTripTransitionException('Só é possível cancelar uma viagem agendada.');
        }

        return $this->withStatus(TripStatus::Canceled);
    }

    private function withStatus(TripStatus $status): self
    {
        return new self(
            $this->id,
            $this->lineId,
            $this->vehicleId,
            $this->driverId,
            $this->departureTime,
            $this->arrivalTime,
            $status,
        );
    }
}
