<?php

declare(strict_types=1);

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Contracts\RequestContext;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepository;
use App\Domain\Shared\Contracts\Clock;

final readonly class RecordAuditLogAction
{
    public function __construct(
        private AuditLogRepository $logs,
        private RequestContext $context,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, scalar|null>|null  $oldValues
     * @param  array<string, scalar|null>|null  $newValues
     */
    public function execute(string $action, string $auditableType, ?string $auditableId, ?array $oldValues, ?array $newValues): AuditLog
    {
        $log = new AuditLog(
            id: $this->logs->nextIdentity(),
            userId: $this->context->userId(),
            action: $action,
            auditableType: $auditableType,
            auditableId: $auditableId,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $this->context->ipAddress(),
            createdAt: $this->clock->now(),
        );

        $this->logs->append($log);

        return $log;
    }
}
