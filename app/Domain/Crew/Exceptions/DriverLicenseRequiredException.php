<?php

declare(strict_types=1);

namespace App\Domain\Crew\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class DriverLicenseRequiredException extends DomainException
{
    /** @param 'cnh_number'|'cnh_category'|'cnh_expiration_date' $field */
    public function __construct(string $field)
    {
        parent::__construct($field, 'Motoristas precisam de número, categoria e validade da CNH.');
    }
}
