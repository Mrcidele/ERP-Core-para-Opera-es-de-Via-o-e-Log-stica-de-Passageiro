<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class LineNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('line_id', 'A linha informada não existe.');
    }
}
