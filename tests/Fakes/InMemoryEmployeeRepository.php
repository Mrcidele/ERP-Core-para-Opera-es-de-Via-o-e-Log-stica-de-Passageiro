<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final class InMemoryEmployeeRepository implements EmployeeRepository
{
    use SlicesPages;

    /** @var array<string, Employee> */
    public array $employees = [];

    private int $sequence = 0;

    public function nextIdentity(): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', ++$this->sequence);
    }

    public function paginate(PageQuery $query): Page
    {
        $employees = array_values(array_filter(
            $this->employees,
            fn (Employee $employee): bool => ($query->status === null || $employee->status->value === $query->status)
                && ($query->search === null || str_contains(mb_strtolower($employee->name), mb_strtolower($query->search))),
        ));

        usort($employees, fn (Employee $a, Employee $b): int => $a->name <=> $b->name);

        return $this->pageOf($employees, $query);
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
