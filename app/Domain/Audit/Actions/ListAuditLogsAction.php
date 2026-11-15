<?php

declare(strict_types=1);

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Repositories\AuditLogRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListAuditLogsAction
{
    public function __construct(
        private AuditLogRepository $logs,
    ) {}

    /**
     * @return Page<AuditLogSummaryDTO>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->logs->paginate($query);
    }
}
