<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class NegativeMaintenanceCostException extends DomainException
{
    public function __construct()
    {
        parent::__construct('cost_cents', 'O custo não pode ser negativo.');
    }
}
