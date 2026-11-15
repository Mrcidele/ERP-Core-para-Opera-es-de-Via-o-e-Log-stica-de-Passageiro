<?php

declare(strict_types=1);

namespace App\Domain\Audit\DTOs;

use App\Domain\Audit\Entities\AuditLog;

/** Registro de auditoria com o nome de quem executou a ação, para a listagem. */
final readonly class AuditLogSummaryDTO
{
    public function __construct(
        public AuditLog $log,
        public ?string $userName,
    ) {}
}
