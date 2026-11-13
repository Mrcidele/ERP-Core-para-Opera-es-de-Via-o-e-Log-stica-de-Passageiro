<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Actions;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Entities\Vehicle;
use App\Domain\Fleet\Exceptions\DuplicateChassisException;
use App\Domain\Fleet\Exceptions\DuplicatePlateException;
use App\Domain\Fleet\Repositories\VehicleRepository;

final readonly class RegisterVehicleAction
{
    public function __construct(
        private VehicleRepository $vehicles,
    ) {}

    public function execute(CreateVehicleDTO $data): Vehicle
    {
        if ($this->vehicles->existsByPlate($data->plate)) {
            throw new DuplicatePlateException($data->plate);
        }

        if ($this->vehicles->existsByChassis($data->chassis)) {
            throw new DuplicateChassisException($data->chassis);
        }

        $vehicle = Vehicle::register($this->vehicles->nextIdentity(), $data);

        $this->vehicles->save($vehicle);

        return $vehicle;
    }
}
