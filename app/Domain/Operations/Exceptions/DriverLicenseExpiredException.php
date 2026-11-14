<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DriverLicenseExpiredException extends DomainException
{
    public function __construct()
    {
        parent::__construct('driver_id', 'A CNH do motorista vence antes da chegada da viagem.');
    }
}
