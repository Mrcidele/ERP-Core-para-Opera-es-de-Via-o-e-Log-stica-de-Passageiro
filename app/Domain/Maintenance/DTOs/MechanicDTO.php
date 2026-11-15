<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\DTOs;

/** Mecânico que pode ser escolhido como responsável por uma ordem de serviço. */
final readonly class MechanicDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
