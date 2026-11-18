<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Entities;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;

final readonly class Vehicle
{
    public function __construct(
        public string $id,
        public string $plate,
        public string $chassis,
        /** Capacidade de carga útil, em quilos. */
        public int $payloadKg,
        public VehicleType $type,
        public VehicleStatus $status,
        public int $currentMileage,
        /** Quilometragem em que a próxima revisão preventiva deve acontecer; null se não programada. */
        public ?int $nextPreventiveMaintenanceKm = null,
        /** Meta de rendimento própria do veículo, em metros por litro; null segue o padrão do tipo. */
        public ?int $minMetersPerLiter = null,
        /** Marca e modelo, ex.: "Volvo FH 540". */
        public ?string $model = null,
    ) {}

    /** Rendimento mínimo esperado, em metros por litro: a meta do veículo ou, sem ela, a do tipo. */
    public function fuelEfficiencyTarget(): int
    {
        return $this->minMetersPerLiter ?? $this->type->defaultMinMetersPerLiter();
    }

    /** O hodômetro já alcançou a quilometragem da próxima revisão preventiva? */
    public function needsPreventiveMaintenance(): bool
    {
        return $this->nextPreventiveMaintenanceKm !== null && $this->currentMileage >= $this->nextPreventiveMaintenanceKm;
    }

    /** Todo veículo recém-cadastrado entra na frota como ativo. */
    public static function register(string $id, CreateVehicleDTO $data): self
    {
        return new self(
            id: $id,
            plate: $data->plate,
            chassis: $data->chassis,
            payloadKg: $data->payloadKg,
            type: $data->type,
            status: VehicleStatus::Active,
            currentMileage: $data->currentMileage,
            nextPreventiveMaintenanceKm: $data->nextPreventiveMaintenanceKm,
            minMetersPerLiter: $data->minMetersPerLiter,
            model: $data->model,
        );
    }
}
