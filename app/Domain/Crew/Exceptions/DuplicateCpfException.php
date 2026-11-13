<?php

declare(strict_types=1);

namespace App\Domain\Crew\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DuplicateCpfException extends DomainException
{
    public function __construct(string $cpf)
    {
        parent::__construct('cpf', "Já existe um funcionário cadastrado com o CPF {$cpf}.");
    }
}
