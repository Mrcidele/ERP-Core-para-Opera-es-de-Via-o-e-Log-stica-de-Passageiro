<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

use DateTimeImmutable;

/** Motorista ativo com a CNH vencida ou perto de vencer. */
final readonly class ExpiringLicenseDTO
{
    public function __construct(
        public string $employeeId,
        public string $name,
        public DateTimeImmutable $expirationDate,
        /** Dias até o vencimento; zero ou negativo quando a CNH já venceu. */
        public int $daysRemaining,
    ) {}
}
