<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\NotFoundException;

final class TripNotFoundException extends NotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct("Viagem {$id} não encontrada.");
    }
}
