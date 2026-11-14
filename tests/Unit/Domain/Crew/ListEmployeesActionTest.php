<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\ListEmployeesAction;
use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use Tests\Fakes\InMemoryEmployeeRepository;

it('lists employees ordered by name', function (): void {
    $employees = new InMemoryEmployeeRepository;
    $employees->save(new Employee('1', 'Maria Souza', '11144477735', EmployeeRole::Mechanic, EmployeeStatus::Active, null, null, null));
    $employees->save(new Employee('2', 'Ana Lima', '52998224725', EmployeeRole::Admin, EmployeeStatus::Active, null, null, null));

    $result = (new ListEmployeesAction($employees))->execute();

    expect(array_map(fn (Employee $employee): string => $employee->name, $result))->toBe(['Ana Lima', 'Maria Souza']);
});
