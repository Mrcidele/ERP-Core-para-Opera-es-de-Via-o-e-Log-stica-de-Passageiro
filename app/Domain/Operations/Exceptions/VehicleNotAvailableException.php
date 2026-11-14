<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class VehicleNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('vehicle_id', 'O veículo informado não existe ou não está ativo.');
    }
}
