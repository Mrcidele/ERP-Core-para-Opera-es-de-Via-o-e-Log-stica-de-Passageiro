<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class InvalidTripTransitionException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct('status', $message);
    }
}
