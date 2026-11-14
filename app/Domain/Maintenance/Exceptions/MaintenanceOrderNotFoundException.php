<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

final class MaintenanceOrderNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Ordem de serviço {$id} não encontrada.");
    }
}
