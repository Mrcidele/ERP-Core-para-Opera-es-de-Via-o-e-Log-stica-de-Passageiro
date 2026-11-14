<?php

declare(strict_types=1);

namespace App\Domain\Operations\Entities;

use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Enums\TripStatus;
use DateTimeImmutable;

final readonly class Trip
{
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
}
