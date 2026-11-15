<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepository;
use App\Infrastructure\Database\Models\AuditLogModel;
use DateTimeZone;
use Illuminate\Support\Str;

final class EloquentAuditLogRepository implements AuditLogRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function append(AuditLog $log): void
    {
        AuditLogModel::query()->create([
            'id' => $log->id,
            'user_id' => $log->userId,
            'action' => $log->action,
            'auditable_type' => $log->auditableType,
            'auditable_id' => $log->auditableId,
            'old_values' => $log->oldValues,
            'new_values' => $log->newValues,
            'ip_address' => $log->ipAddress,
            'created_at' => $log->createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP'),
        ]);
    }

    public function latest(int $limit): array
    {
        return array_values(
            AuditLogModel::query()
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (AuditLogModel $model): AuditLogSummaryDTO => new AuditLogSummaryDTO(
                    log: new AuditLog(
                        id: $model->id,
                        userId: $model->user_id,
                        action: $model->action,
                        auditableType: $model->auditable_type,
                        auditableId: $model->auditable_id,
                        oldValues: $model->old_values,
                        newValues: $model->new_values,
                        ipAddress: $model->ip_address,
                        createdAt: $model->created_at->toDateTimeImmutable(),
                    ),
                    userName: $model->user?->name,
                ))
                ->all(),
        );
    }
}
