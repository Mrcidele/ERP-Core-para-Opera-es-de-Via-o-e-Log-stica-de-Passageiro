<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\ListEmployeesAction;
use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\InMemoryEmployeeRepository;

it('lists employees ordered by name', function (): void {
    $employees = new InMemoryEmployeeRepository;
    $employees->save(new Employee('1', 'Maria Souza', '11144477735', EmployeeRole::Mechanic, EmployeeStatus::Active, null, null, null));
    $employees->save(new Employee('2', 'Ana Lima', '52998224725', EmployeeRole::Admin, EmployeeStatus::Active, null, null, null));

    $result = (new ListEmployeesAction($employees))->execute();

    expect(array_map(fn (Employee $employee): string => $employee->name, $result->items))->toBe(['Ana Lima', 'Maria Souza'])
        ->and($result->total)->toBe(2)
        ->and($result->lastPage())->toBe(1);
});

it('returns only the requested page and reports the total', function (): void {
    $employees = new InMemoryEmployeeRepository;
    foreach (['Ana', 'Bia', 'Caio', 'Davi', 'Eva'] as $index => $name) {
        $employees->save(new Employee((string) $index, $name, "0000000000{$index}", EmployeeRole::Admin, EmployeeStatus::Active, null, null, null));
    }

    $result = (new ListEmployeesAction($employees))->execute(new PageQuery(page: 2, perPage: 2));

    expect(array_map(fn (Employee $employee): string => $employee->name, $result->items))->toBe(['Caio', 'Davi'])
        ->and($result->total)->toBe(5)
        ->and($result->page)->toBe(2)
        ->and($result->lastPage())->toBe(3);
});
