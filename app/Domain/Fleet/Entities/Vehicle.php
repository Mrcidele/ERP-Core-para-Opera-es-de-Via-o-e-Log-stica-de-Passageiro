<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Entities;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;

final readonly class Vehicle
{
    public function __construct(
        public string $id,
        public string $plate,
        public string $chassis,
        public int $capacity,
        public VehicleClass $classType,
        public VehicleStatus $status,
        public int $currentMileage,
        /** Quilometragem em que a próxima revisão preventiva deve acontecer; null se não programada. */
        public ?int $nextPreventiveMaintenanceKm = null,
    ) {}

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
            capacity: $data->capacity,
            classType: $data->classType,
            status: VehicleStatus::Active,
            currentMileage: $data->currentMileage,
            nextPreventiveMaintenanceKm: $data->nextPreventiveMaintenanceKm,
        );
    }
}
