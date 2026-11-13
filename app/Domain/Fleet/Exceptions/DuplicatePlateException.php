<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DuplicatePlateException extends DomainException
{
    public function __construct(string $plate)
    {
        parent::__construct('plate', "Já existe um veículo cadastrado com a placa {$plate}.");
    }
}
