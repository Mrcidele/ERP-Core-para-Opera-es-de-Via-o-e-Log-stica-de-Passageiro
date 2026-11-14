<?php

declare(strict_types=1);

namespace App\Domain\Operations\Contracts;

/** O que Operations precisa saber da Frota, sem depender das classes do módulo Fleet. */
interface FleetGateway
{
    /** O veículo existe, não foi excluído e está com status ativo? */
    public function isActiveVehicle(string $vehicleId): bool;
}
