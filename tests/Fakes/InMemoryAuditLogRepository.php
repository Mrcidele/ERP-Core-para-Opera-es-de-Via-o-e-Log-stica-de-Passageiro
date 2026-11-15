<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepository;

final class InMemoryAuditLogRepository implements AuditLogRepository
{
    /** @var list<AuditLog> */
    public array $logs = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function append(AuditLog $log): void
    {
        $this->logs[] = $log;
    }

    public function latest(int $limit): array
    {
        return array_map(
            fn (AuditLog $log): AuditLogSummaryDTO => new AuditLogSummaryDTO($log, $log->userId === null ? null : 'Usuário'),
            array_slice(array_reverse($this->logs), 0, $limit),
        );
    }
}
