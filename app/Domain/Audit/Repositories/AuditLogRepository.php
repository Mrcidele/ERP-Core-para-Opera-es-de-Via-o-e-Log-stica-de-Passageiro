<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repositories;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;

/** Registros de auditoria só são acrescentados; não há edição nem exclusão. */
interface AuditLogRepository
{
    public function nextIdentity(): string;

    public function append(AuditLog $log): void;

    /**
     * Os registros mais recentes primeiro.
     *
     * @return list<AuditLogSummaryDTO>
     */
    public function latest(int $limit): array;
}
