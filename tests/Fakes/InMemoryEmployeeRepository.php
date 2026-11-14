<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;

final class InMemoryEmployeeRepository implements EmployeeRepository
{
    /** @var array<string, Employee> */
    public array $employees = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function all(): array
    {
        $employees = array_values($this->employees);

        usort($employees, fn (Employee $a, Employee $b): int => $a->name <=> $b->name);

        return $employees;
    }

    public function existsByCpf(string $cpf): bool
    {
        return array_any($this->employees, fn (Employee $e): bool => $e->cpf === $cpf);
    }

    public function existsByCnhNumber(string $cnhNumber): bool
    {
        return array_any($this->employees, fn (Employee $e): bool => $e->cnhNumber === $cnhNumber);
    }

    public function save(Employee $employee): void
    {
        $this->employees[$employee->id] = $employee;
    }
}
