<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Audit;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuditLogSummaryDTO $resource
 */
final class AuditLogResource extends JsonResource
{
    public function __construct(AuditLogSummaryDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: string,
     *     action: string,
     *     auditable_type: string,
     *     auditable_id: string|null,
     *     old_values: array<string, scalar|null>|null,
     *     new_values: array<string, scalar|null>|null,
     *     ip_address: string|null,
     *     created_at: string,
     *     user: array{id: string, name: string|null}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource->log;

        return [
            'id' => $log->id,
            'action' => $log->action,
            'auditable_type' => $log->auditableType,
            'auditable_id' => $log->auditableId,
            'old_values' => $log->oldValues,
            'new_values' => $log->newValues,
            'ip_address' => $log->ipAddress,
            'created_at' => $log->createdAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'user' => $log->userId === null ? null : ['id' => $log->userId, 'name' => $this->resource->userName],
        ];
    }
}
