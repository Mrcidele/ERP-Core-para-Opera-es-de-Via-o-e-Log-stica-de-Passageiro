<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListEmployeesAction
{
    public function __construct(
        private EmployeeRepository $employees,
    ) {}

    /**
     * @return Page<Employee>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->employees->paginate($query);
    }
}
