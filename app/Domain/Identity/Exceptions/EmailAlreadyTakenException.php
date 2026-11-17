<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class EmailAlreadyTakenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('email', 'Já existe um usuário com este e-mail.');
    }
}
