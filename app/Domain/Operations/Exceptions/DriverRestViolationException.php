<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Operations\Entities\Trip;
use App\Domain\Shared\Exceptions\DomainException;

final class DriverRestViolationException extends DomainException
{
    public function __construct()
    {
        parent::__construct('driver_id', sprintf(
            'O motorista precisa de pelo menos %d horas de descanso entre o fim de uma viagem e o início da seguinte.',
            Trip::DRIVER_REST_HOURS,
        ));
    }
}
