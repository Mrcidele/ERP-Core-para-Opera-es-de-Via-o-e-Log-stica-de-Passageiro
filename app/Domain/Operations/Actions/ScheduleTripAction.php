<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Contracts\CrewGateway;
use App\Domain\Operations\Contracts\FleetGateway;
use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Entities\Trip;
use App\Domain\Operations\Exceptions\DriverLicenseExpiredException;
use App\Domain\Operations\Exceptions\DriverNotAvailableException;
use App\Domain\Operations\Exceptions\DriverRestViolationException;
use App\Domain\Operations\Exceptions\DriverScheduleConflictException;
use App\Domain\Operations\Exceptions\InvalidTripPeriodException;
use App\Domain\Operations\Exceptions\LineNotFoundException;
use App\Domain\Operations\Exceptions\TripInThePastException;
use App\Domain\Operations\Exceptions\VehicleNotAvailableException;
use App\Domain\Operations\Exceptions\VehicleScheduleConflictException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Operations\Repositories\TripRepository;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\TripScheduled;

final readonly class ScheduleTripAction
{
    public function __construct(
        private TripRepository $trips,
        private LineRepository $lines,
        private FleetGateway $fleet,
        private CrewGateway $crew,
        private Clock $clock,
        private TransactionRunner $transaction,
        private EventDispatcher $events,
    ) {}

    public function execute(ScheduleTripDTO $data): Trip
    {
        if ($data->departureTime <= $this->clock->now()) {
            throw new TripInThePastException;
        }

        if ($data->arrivalTime <= $data->departureTime) {
            throw new InvalidTripPeriodException;
        }

        return $this->transaction->run(function () use ($data): Trip {
            // A rota fica travada até a viagem ser gravada: uma exclusão simultânea espera por este agendamento,
            // ou termina antes e a rota deixa de ser encontrada aqui.
            if ($this->lines->lockActive($data->lineId) === null) {
                throw new LineNotFoundException;
            }

            if (! $this->fleet->isActiveVehicle($data->vehicleId)) {
                throw new VehicleNotAvailableException;
            }

            if (! $this->crew->isActiveDriver($data->driverId)) {
                throw new DriverNotAvailableException;
            }

            if (! $this->crew->isDriverLicenseValidUntil($data->driverId, $data->arrivalTime)) {
                throw new DriverLicenseExpiredException;
            }

            if ($this->trips->hasVehicleConflict($data->vehicleId, $data->departureTime, $data->arrivalTime)) {
                throw new VehicleScheduleConflictException;
            }

            if ($this->trips->hasDriverConflict($data->driverId, $data->departureTime, $data->arrivalTime)) {
                throw new DriverScheduleConflictException;
            }

            if ($this->trips->hasDriverRestConflict($data->driverId, $data->departureTime, $data->arrivalTime, Trip::DRIVER_REST_HOURS)) {
                throw new DriverRestViolationException;
            }

            $trip = Trip::schedule($this->trips->nextIdentity(), $data);

            $this->trips->save($trip);
            $this->events->dispatch(new TripScheduled($trip->id, $trip->lineId, $trip->vehicleId, $trip->driverId, $trip->departureTime, $trip->arrivalTime));

            return $trip;
        });
    }
}
