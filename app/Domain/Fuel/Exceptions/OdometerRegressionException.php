<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class OdometerRegressionException extends DomainException
{
    public function __construct(int $lastOdometerKm)
    {
        parent::__construct('odometer_km', "O hodômetro não pode ser menor que o do último abastecimento deste veículo ({$lastOdometerKm} km).");
    }
}
