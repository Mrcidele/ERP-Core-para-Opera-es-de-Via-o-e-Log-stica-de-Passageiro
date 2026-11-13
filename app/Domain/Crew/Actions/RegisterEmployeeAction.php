<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions;

use App\Domain\Crew\DTOs\CreateEmployeeDTO;
use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Exceptions\DriverLicenseRequiredException;
use App\Domain\Crew\Exceptions\DuplicateCnhException;
use App\Domain\Crew\Exceptions\DuplicateCpfException;
use App\Domain\Crew\Exceptions\ExpiredDriverLicenseException;
use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Shared\Contracts\Clock;

final readonly class RegisterEmployeeAction
{
    public function __construct(
        private EmployeeRepository $employees,
        private Clock $clock,
    ) {}

    public function execute(CreateEmployeeDTO $data): Employee
    {
        if ($data->role === EmployeeRole::Driver) {
            $this->ensureDriverHasLicense($data);
        }

        // A validade precisa ser posterior a hoje, para qualquer cargo que informe CNH.
        if ($data->cnhExpirationDate !== null && $data->cnhExpirationDate <= $this->clock->today()) {
            throw new ExpiredDriverLicenseException;
        }

        if ($this->employees->existsByCpf($data->cpf)) {
            throw new DuplicateCpfException($data->cpf);
        }

        if ($data->cnhNumber !== null && $this->employees->existsByCnhNumber($data->cnhNumber)) {
            throw new DuplicateCnhException($data->cnhNumber);
        }

        $employee = Employee::register($this->employees->nextIdentity(), $data);

        $this->employees->save($employee);

        return $employee;
    }

    private function ensureDriverHasLicense(CreateEmployeeDTO $data): void
    {
        if ($data->cnhNumber === null) {
            throw new DriverLicenseRequiredException('cnh_number');
        }

        if ($data->cnhCategory === null) {
            throw new DriverLicenseRequiredException('cnh_category');
        }

        if ($data->cnhExpirationDate === null) {
            throw new DriverLicenseRequiredException('cnh_expiration_date');
        }
    }
}
