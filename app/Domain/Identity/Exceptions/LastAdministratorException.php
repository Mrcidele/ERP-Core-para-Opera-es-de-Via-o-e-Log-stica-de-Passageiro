<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/** Rebaixar ou desativar este usuário deixaria a empresa sem nenhum administrador ativo. */
final class LastAdministratorException extends DomainException
{
    public function __construct(string $field = 'role')
    {
        parent::__construct($field, 'A empresa precisa de pelo menos um administrador ativo. Promova outro usuário a administrador antes.');
    }
}
