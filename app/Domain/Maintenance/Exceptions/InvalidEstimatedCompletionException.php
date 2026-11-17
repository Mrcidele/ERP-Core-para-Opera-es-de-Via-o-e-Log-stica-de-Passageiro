<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class InvalidEstimatedCompletionException extends DomainException
{
    public function __construct()
    {
        parent::__construct('estimated_completion_at', 'A previsão de término deve ser posterior à abertura da ordem de serviço.');
    }
}
