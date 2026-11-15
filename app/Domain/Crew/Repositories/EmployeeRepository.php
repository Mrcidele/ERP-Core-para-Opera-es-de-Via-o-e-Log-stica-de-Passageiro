<?php

declare(strict_types=1);

namespace App\Domain\Crew\Repositories;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

interface EmployeeRepository
{
    public function nextIdentity(): string;

    /**
     * Funcionários ordenados por nome. Busca por nome ou CPF; filtro por status.
     *
     * @return Page<Employee>
     */
    public function paginate(PageQuery $query): Page;

    public function existsByCpf(string $cpf): bool;

    public function existsByCnhNumber(string $cnhNumber): bool;

    public function save(Employee $employee): void;
}
