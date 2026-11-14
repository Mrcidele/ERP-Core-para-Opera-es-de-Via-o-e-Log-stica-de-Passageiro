<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class InvalidTripPeriodException extends DomainException
{
    public function __construct()
    {
        parent::__construct('arrival_time', 'A chegada deve ser depois da partida.');
    }
}
