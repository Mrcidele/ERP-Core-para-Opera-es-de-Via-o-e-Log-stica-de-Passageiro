<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Contracts;

/** O que Maintenance precisa da Frota, sem depender das classes do módulo Fleet. */
interface FleetGateway
{
    /** O veículo existe e não foi excluído? */
    public function vehicleExists(string $vehicleId): bool;

    /** Tira o veículo de circulação: ele deixa de poder ser escalado em viagens. */
    public function markVehicleInMaintenance(string $vehicleId): void;

    /** Devolve o veículo à operação. */
    public function markVehicleActive(string $vehicleId): void;
}
