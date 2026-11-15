<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class LineHasActiveTripsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('line', 'Esta linha tem viagens agendadas ou em andamento. Conclua ou cancele as viagens antes de excluí-la.');
    }
}
