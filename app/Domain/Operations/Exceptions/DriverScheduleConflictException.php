<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DriverScheduleConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('driver_id', 'O motorista já tem outra viagem nesse período.');
    }
}
