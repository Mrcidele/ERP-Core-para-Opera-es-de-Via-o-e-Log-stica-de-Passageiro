<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class MechanicNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('mechanic_id', 'O funcionário informado não existe, não é mecânico ou não está ativo.');
    }
}
