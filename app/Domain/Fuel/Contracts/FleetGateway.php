<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Contracts;

/** O que o Abastecimento precisa da Frota. */
interface FleetGateway
{
    /**
     * Trava o veículo até o fim da transação em curso e devolve a quilometragem atual dele;
     * null quando o veículo não existe ou foi excluído. Dois abastecimentos do mesmo veículo passam um de cada vez.
     */
    public function lockVehicleMileage(string $vehicleId): ?int;

    /** Leva o hodômetro do veículo até $mileageKm; nunca o reduz. */
    public function raiseVehicleMileage(string $vehicleId, int $mileageKm): void;
}
