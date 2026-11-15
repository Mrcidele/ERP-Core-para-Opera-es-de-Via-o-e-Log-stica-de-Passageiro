<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

/** Linha inexistente (ou já excluída) em uma edição ou exclusão: responde 404. */
final class LineRecordNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Linha {$id} não encontrada.");
    }
}
