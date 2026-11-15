<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

/** Quais blocos do painel o usuário pode ver, conforme as permissões dele. */
final readonly class DashboardScopeDTO
{
    public function __construct(
        public bool $fleet,
        public bool $operations,
        public bool $crew,
        public bool $maintenance,
    ) {}
}
