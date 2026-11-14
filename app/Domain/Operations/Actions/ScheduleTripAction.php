<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Contracts\CrewGateway;
use App\Domain\Operations\Contracts\FleetGateway;
use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Exceptions\DriverNotAvailableException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\InvalidTripPeriodException;
use App\Domain\Operations\Exceptions\LineNotFoundException;
use App\Domain\Operations\Exceptions\TripInThePastException;
use App\Domain\Operations\Exceptions\VehicleNotAvailableException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Contracts\Clock;

final readonly class ScheduleTripAction
{
    public function __construct(
        private TripRepository $trips,
        private LineRepository $lines,
        private FleetGateway $fleet,
        private CrewGateway $crew,
        private Clock $clock,
    ) {}

    public function execute(ScheduleTripDTO $data): Trip
    {
        if ($data->departureTime <= $this->clock->now()) {
            throw new TripInThePastException;
        }

        if ($data->arrivalTime <= $data->departureTime) {
            throw new InvalidTripPeriodException;
        }

        if (! $this->lines->exists($data->lineId)) {
            throw new LineNotFoundException;
        }

        if (! $this->fleet->isActiveVehicle($data->vehicleId)) {
            throw new VehicleNotAvailableException;
        }

        if (! $this->crew->isActiveDriver($data->driverId)) {
            throw new DriverNotAvailableException;
        }

        if ($this->trips->hasVehicleConflict($data->vehicleId, $data->departureTime, $data->arrivalTime)) {
            throw new VehicleScheduleConflictException;
        }

        if ($this->trips->hasDriverConflict($data->driverId, $data->departureTime, $data->arrivalTime)) {
            throw new DriverScheduleConflictException;
        }

        $trip = Trip::schedule($this->trips->nextIdentity(), $data);

        $this->trips->save($trip);

        return $trip;
    }
}
