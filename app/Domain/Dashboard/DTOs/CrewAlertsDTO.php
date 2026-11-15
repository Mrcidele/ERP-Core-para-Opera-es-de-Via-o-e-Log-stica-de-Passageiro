<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Alertas da tripulação. */
final readonly class CrewAlertsDTO
{
    public function __construct(
        /** @var list<ExpiringLicenseDTO> */
        public array $expiringLicenses,
    ) {}
}
