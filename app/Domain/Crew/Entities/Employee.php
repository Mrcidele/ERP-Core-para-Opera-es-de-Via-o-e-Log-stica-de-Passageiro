<?php

declare(strict_types=1);

namespace App\Domain\Crew\Entities;

use App\Domain\Crew\DTOs\CreateEmployeeDTO;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use DateTimeImmutable;

final readonly class Employee
{
    public function __construct(
        public string $id,
        public string $name,
        public string $cpf,
        public EmployeeRole $role,
        public EmployeeStatus $status,
        public ?string $cnhNumber,
        public ?string $cnhCategory,
        public ?DateTimeImmutable $cnhExpirationDate,
    ) {}

    public static function register(string $id, CreateEmployeeDTO $data): self
    {
        return new self(
            id: $id,
            name: $data->name,
            cpf: $data->cpf,
            role: $data->role,
            status: $data->status,
            cnhNumber: $data->cnhNumber,
            cnhCategory: $data->cnhCategory,
            cnhExpirationDate: $data->cnhExpirationDate,
        );
    }
}
