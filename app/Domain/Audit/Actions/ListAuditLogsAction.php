<?php

declare(strict_types=1);

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Repositories\AuditLogRepository;

final readonly class ListAuditLogsAction
{
    public const MAX_LIMIT = 200;

    public function __construct(
        private AuditLogRepository $logs,
    ) {}

    /**
     * @return list<AuditLogSummaryDTO>
     */
    public function execute(int $limit = 100): array
    {
        return $this->logs->latest(max(1, min($limit, self::MAX_LIMIT)));
    }
}
