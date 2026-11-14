<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DriverNotAvailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('driver_id', 'O funcionário informado não existe, não é motorista ou não está ativo.');
    }
}
