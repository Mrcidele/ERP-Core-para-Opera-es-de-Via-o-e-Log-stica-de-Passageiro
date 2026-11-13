<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\DTOs\UpdateVehicleDTO;
use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use App\Domain\Fleet\Exceptions\MileageDecreaseException;
use App\Domain\Fleet\Exceptions\VehicleNotFoundException;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class UpdateVehicleAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    public function execute(UpdateVehicleDTO $data): Vehicle
    {
        $current = $this->vehicles->findById($data->id) ?? throw new VehicleNotFoundException($data->id);

        if ($this->vehicles->existsByPlate($data->plate, exceptId: $data->id)) {
            throw new DuplicatePlateException($data->plate);
        }

        if ($this->vehicles->existsByChassis($data->chassis, exceptId: $data->id)) {
            throw new DuplicateChassisException($data->chassis);
        }

        // Hodômetro só avança.
        if ($data->currentMileage < $current->currentMileage) {
            throw new MileageDecreaseException($current->currentMileage);
        }

        $vehicle = new Vehicle(
            id: $current->id,
            plate: $data->plate,
            chassis: $data->chassis,
            capacity: $data->capacity,
            classType: $data->classType,
            status: $data->status,
            currentMileage: $data->currentMileage,
        );

        $this->vehicles->save($vehicle);

        return $vehicle;
    }
}
