<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DuplicateChassisException extends DomainException
{
    public function __construct(string $chassis)
    {
        parent::__construct('chassis', "Já existe um veículo cadastrado com o chassi {$chassis}.");
    }
}
