<?php

declare(strict_types=1);

namespace App\Domain\Crew\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DuplicateCnhException extends DomainException
{
    public function __construct(string $cnhNumber)
    {
        parent::__construct('cnh_number', "Já existe um funcionário cadastrado com a CNH {$cnhNumber}.");
    }
}
