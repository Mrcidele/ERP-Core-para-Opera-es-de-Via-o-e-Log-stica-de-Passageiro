<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

final class UserNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Usuário {$id} não encontrado.");
    }
}
