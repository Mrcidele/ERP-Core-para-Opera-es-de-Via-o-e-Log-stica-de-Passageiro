<?php

declare(strict_types=1);

namespace App\Domain\Audit\Repositories;

use App\Domain\Audit\DTOs\AuditLogSummaryDTO;
use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

/** Registros de auditoria só são acrescentados; não há edição nem exclusão. */
interface AuditLogRepository
{
    public function nextIdentity(): string;

    public function append(AuditLog $log): void;

    /**
     * Registros do mais recente para o mais antigo. Busca por ação, entidade, usuário ou IP.
     *
     * @return Page<AuditLogSummaryDTO>
     */
    public function paginate(PageQuery $query): Page;
}
