<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class InvalidMaintenanceTransitionException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct('status', $message);
    }
}
