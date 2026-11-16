<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepository;
use App\Domain\Shared\Enums\AuditAction;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\AuditLogModel;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentAuditLogRepository implements AuditLogRepository
{
    use PaginatesQueries;

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

    public function paginate(PageQuery $query): Page
    {
        $builder = AuditLogModel::query()
            ->with('user')
            ->when($query->search !== null, function (Builder $logs) use ($query): void {
                $term = $this->likeTerm((string) $query->search);
                // O nome da ação em português não está no banco: o termo é traduzido para os códigos que ele descreve.
                $labeled = AuditAction::codesMatchingLabel((string) $query->search);

                $logs->where(fn (Builder $match) => $match
                    ->where('action', 'ilike', $term)
                    ->when($labeled !== [], fn (Builder $byLabel) => $byLabel->orWhereIn('action', $labeled))
                    ->orWhere('auditable_type', 'ilike', $term)
                    ->orWhere('ip_address', 'ilike', $term)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'ilike', $term)));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $this->paginateQuery($builder, $query, fn (AuditLogModel $model): AuditLogSummaryDTO => new AuditLogSummaryDTO(
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
        ));
    }
}
