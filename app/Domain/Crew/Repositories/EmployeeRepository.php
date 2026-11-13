<?php

declare(strict_types=1);

namespace App\Domain\Crew\Repositories;

use App\Domain\Crew\Entities\Employee;

interface EmployeeRepository
{
    public function nextIdentity(): string;

    public function existsByCpf(string $cpf): bool;

    public function existsByCnhNumber(string $cnhNumber): bool;

    public function save(Employee $employee): void;
}
