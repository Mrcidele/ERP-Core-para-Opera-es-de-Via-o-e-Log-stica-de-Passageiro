<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/** O rendimento compara cada abastecimento com o anterior: os registros de um veículo entram em ordem de data. */
final class FuelLogOutOfOrderException extends DomainException
{
    public function __construct()
    {
        parent::__construct('fueled_at', 'A data do abastecimento não pode ser anterior à do último abastecimento deste veículo.');
    }
}
