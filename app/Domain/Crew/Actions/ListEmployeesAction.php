<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;

final readonly class ListEmployeesAction
{
    public function __construct(
        private EmployeeRepository $employees,
    ) {}

    /**
     * @return list<Employee>
     */
    public function execute(): array
    {
        return $this->employees->all();
    }
}
