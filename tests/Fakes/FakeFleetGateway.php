<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\Contracts\FleetGateway;

final class FakeFleetGateway implements FleetGateway
{
    /** @param  list<string>  $activeVehicleIds */
    public function __construct(public array $activeVehicleIds = []) {}

    public function isActiveVehicle(string $vehicleId): bool
    {
        return in_array($vehicleId, $this->activeVehicleIds, true);
    }
}
