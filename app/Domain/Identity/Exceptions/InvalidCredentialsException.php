<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        // Mesma mensagem para e-mail inexistente e senha errada, para não revelar quais e-mails têm conta.
        parent::__construct('email', 'E-mail ou senha incorretos.');
    }
}
