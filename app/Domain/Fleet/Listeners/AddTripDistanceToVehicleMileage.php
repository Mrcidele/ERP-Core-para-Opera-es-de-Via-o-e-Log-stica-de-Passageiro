<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Listeners;

use App\Domain\Fleet\Actions\IncreaseVehicleMileageAction;
use App\Domain\Shared\Events\TripCompleted;

final readonly class AddTripDistanceToVehicleMileage
{
    public function __construct(
        private IncreaseVehicleMileageAction $increaseMileage,
    ) {}

    public function handle(TripCompleted $event): void
    {
        $this->increaseMileage->execute($event->vehicleId, $event->distanceKm);
    }
}
