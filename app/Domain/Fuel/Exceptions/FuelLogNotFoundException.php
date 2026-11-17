<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

/** Abastecimento inexistente ou já excluído: responde 404. */
final class FuelLogNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Abastecimento {$id} não encontrado.");
    }
}
