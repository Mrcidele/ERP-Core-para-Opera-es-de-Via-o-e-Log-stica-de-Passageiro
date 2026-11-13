<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class MileageDecreaseException extends DomainException
{
    public function __construct(int $current)
    {
        parent::__construct('current_mileage', "A quilometragem não pode ser menor que a atual ({$current} km).");
    }
}
