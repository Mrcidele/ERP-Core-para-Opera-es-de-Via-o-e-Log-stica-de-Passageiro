<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Maintenance\Contracts\FleetGateway as MaintenanceFleetGateway;
use App\Domain\Operations\Contracts\FleetGateway as OperationsFleetGateway;

/** Frota em memória vista pelos módulos Operations e Maintenance. */
final class FakeFleetGateway implements MaintenanceFleetGateway, OperationsFleetGateway
{
    /** @var array<string, 'active'|'maintenance'|'inactive'> id do veículo => status */
    public array $statuses = [];

    /** @param  list<string>  $activeVehicleIds */
    public function __construct(array $activeVehicleIds = [])
    {
        foreach ($activeVehicleIds as $id) {
            $this->statuses[$id] = 'active';
        }
    }

    public function isActiveVehicle(string $vehicleId): bool
    {
        return ($this->statuses[$vehicleId] ?? null) === 'active';
    }

    public function vehicleExists(string $vehicleId): bool
    {
        return isset($this->statuses[$vehicleId]);
    }

    public function markVehicleInMaintenance(string $vehicleId): void
    {
        $this->statuses[$vehicleId] = 'maintenance';
    }

    public function markVehicleActive(string $vehicleId): void
    {
        $this->statuses[$vehicleId] = 'active';
    }
}
