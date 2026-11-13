<?php

declare(strict_types=1);

namespace App\Domain\Fleet\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

final class VehicleNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Veículo {$id} não encontrado.");
    }
}
