<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class IncreaseVehicleMileageAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    /**
     * Soma a distância percorrida ao hodômetro. Devolve null quando o veículo já foi excluído
     * da frota: não há hodômetro a atualizar e isso não deve impedir a conclusão da viagem.
     */
    public function execute(string $vehicleId, int $distanceKm): ?Vehicle
    {
        $vehicle = $this->vehicles->findById($vehicleId);

        if ($vehicle === null) {
            return null;
        }

        if ($distanceKm <= 0) {
            return $vehicle;
        }

        $updated = new Vehicle(
            id: $vehicle->id,
            plate: $vehicle->plate,
            chassis: $vehicle->chassis,
            payloadKg: $vehicle->payloadKg,
            type: $vehicle->type,
            status: $vehicle->status,
            currentMileage: $vehicle->currentMileage + $distanceKm,
            nextPreventiveMaintenanceKm: $vehicle->nextPreventiveMaintenanceKm,
            minMetersPerLiter: $vehicle->minMetersPerLiter,
            model: $vehicle->model,
        );

        $this->vehicles->save($updated);

        return $updated;
    }
}
