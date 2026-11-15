<?php

declare(strict_types=1);

namespace App\Domain\Audit\Entities;

use DateTimeImmutable;

final readonly class AuditLog
{
    /**
     * @param  array<string, scalar|null>|null  $oldValues
     * @param  array<string, scalar|null>|null  $newValues
     */
    public function __construct(
        public string $id,
        public ?string $userId,
        public string $action,
        public string $auditableType,
        public ?string $auditableId,
        public ?array $oldValues,
        public ?array $newValues,
        public ?string $ipAddress,
        public DateTimeImmutable $createdAt,
    ) {}
}
