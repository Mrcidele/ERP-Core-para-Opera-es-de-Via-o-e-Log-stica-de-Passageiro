<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class TripInThePastException extends DomainException
{
    public function __construct()
    {
        parent::__construct('departure_time', 'A partida deve ser no futuro.');
    }
}
