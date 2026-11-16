<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Fuel\Contracts\FleetGateway as FuelFleetGateway;
use App\Domain\Maintenance\Contracts\FleetGateway as MaintenanceFleetGateway;
use App\Domain\Operations\Contracts\FleetGateway as OperationsFleetGateway;

/** Frota em memória vista pelos módulos Operations e Maintenance. */
final class FakeFleetGateway implements FuelFleetGateway, MaintenanceFleetGateway, OperationsFleetGateway
{
    /** @var array<string, 'active'|'maintenance'|'inactive'> id do veículo => status */
    public array $statuses = [];

    /** @var array<string, int> id do veículo => quilometragem */
    public array $mileages = [];

    /** @var list<string> veículos travados, na ordem */
    public array $locked = [];

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

    public function lockVehicleMileage(string $vehicleId): ?int
    {
        if (! isset($this->statuses[$vehicleId])) {
            return null;
        }

        $this->locked[] = $vehicleId;

        return $this->mileages[$vehicleId] ?? 0;
    }

    public function raiseVehicleMileage(string $vehicleId, int $mileageKm): void
    {
        $this->mileages[$vehicleId] = max($this->mileages[$vehicleId] ?? 0, $mileageKm);
    }

    public function markVehicleActive(string $vehicleId): void
    {
        $this->statuses[$vehicleId] = 'active';
    }
}
