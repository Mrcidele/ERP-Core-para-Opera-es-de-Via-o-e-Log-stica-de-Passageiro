<?php

declare(strict_types=1);

namespace App\Domain\Crew\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class ExpiredDriverLicenseException extends DomainException
{
    public function __construct()
    {
        parent::__construct('cnh_expiration_date', 'A CNH está vencida ou vence hoje. Informe uma validade posterior a hoje.');
    }
}
