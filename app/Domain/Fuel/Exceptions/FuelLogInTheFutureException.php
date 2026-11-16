<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class FuelLogInTheFutureException extends DomainException
{
    public function __construct()
    {
        parent::__construct('fueled_at', 'A data do abastecimento não pode estar no futuro.');
    }
}
