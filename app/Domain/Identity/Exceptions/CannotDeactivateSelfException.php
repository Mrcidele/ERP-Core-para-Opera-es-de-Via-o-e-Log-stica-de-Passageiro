<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class CannotDeactivateSelfException extends DomainException
{
    public function __construct()
    {
        parent::__construct('user', 'Você não pode desativar o próprio acesso.');
    }
}
