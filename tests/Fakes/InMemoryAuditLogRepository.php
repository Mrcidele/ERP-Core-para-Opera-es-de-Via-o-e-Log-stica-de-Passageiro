<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final class InMemoryAuditLogRepository implements AuditLogRepository
{
    use SlicesPages;

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

    public function paginate(PageQuery $query): Page
    {
        $logs = array_values(array_filter(
            array_reverse($this->logs),
            fn (AuditLog $log): bool => $query->search === null || str_contains($log->action, $query->search),
        ));

        return $this->pageOf(array_map(
            fn (AuditLog $log): AuditLogSummaryDTO => new AuditLogSummaryDTO($log, $log->userId === null ? null : 'Usuário'),
            $logs,
        ), $query);
    }
}
