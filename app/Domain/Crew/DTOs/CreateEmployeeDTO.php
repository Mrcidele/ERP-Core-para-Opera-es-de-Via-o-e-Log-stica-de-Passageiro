<?php

declare(strict_types=1);

namespace App\Domain\Crew\DTOs;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use DateTimeImmutable;

final readonly class CreateEmployeeDTO
{
    public function __construct(
        public string $name,
        public string $cpf,
        public EmployeeRole $role,
        public EmployeeStatus $status,
        public ?string $cnhNumber = null,
        public ?string $cnhCategory = null,
        public ?DateTimeImmutable $cnhExpirationDate = null,
    ) {}
}
