<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\RegisterEmployeeAction;
use App\Domain\Crew\DTOs\CreateEmployeeDTO;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Domain\Crew\Exceptions\DriverLicenseRequiredException;
use App\Domain\Crew\Exceptions\DuplicateCnhException;
use App\Domain\Crew\Exceptions\DuplicateCpfException;
use App\Domain\Crew\Exceptions\ExpiredDriverLicenseException;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryEmployeeRepository;

function driverData(
    string $cpf = '52998224725',
    ?string $cnhNumber = '12345678901',
    ?string $cnhCategory = 'D',
    ?string $cnhExpiration = '2027-06-30',
): CreateEmployeeDTO {
    return new CreateEmployeeDTO(
        name: 'João da Silva',
        cpf: $cpf,
        role: EmployeeRole::Driver,
        status: EmployeeStatus::Active,
        cnhNumber: $cnhNumber,
        cnhCategory: $cnhCategory,
        cnhExpirationDate: $cnhExpiration === null ? null : new DateTimeImmutable($cnhExpiration),
    );
}

beforeEach(function (): void {
    $this->repository = new InMemoryEmployeeRepository;
    $this->action = new RegisterEmployeeAction($this->repository, new FixedClock('2026-11-13'));
});

it('registers a driver with a valid license', function (): void {
    $employee = $this->action->execute(driverData());

    expect($employee->name)->toBe('João da Silva')
        ->and($employee->cpf)->toBe('52998224725')
        ->and($employee->role)->toBe(EmployeeRole::Driver)
        ->and($employee->status)->toBe(EmployeeStatus::Active)
        ->and($employee->cnhNumber)->toBe('12345678901')
        ->and($employee->cnhCategory)->toBe('D')
        ->and($employee->cnhExpirationDate?->format('Y-m-d'))->toBe('2027-06-30')
        ->and($this->repository->employees[$employee->id])->toBe($employee);
});

it('registers a mechanic without a license', function (): void {
    $employee = $this->action->execute(new CreateEmployeeDTO(
        name: 'Maria Souza',
        cpf: '11144477735',
        role: EmployeeRole::Mechanic,
        status: EmployeeStatus::Active,
    ));

    expect($employee->cnhNumber)->toBeNull()
        ->and($this->repository->employees)->toHaveCount(1);
});

it('rejects a duplicated CPF', function (): void {
    $this->action->execute(driverData());

    expect(fn () => $this->action->execute(driverData(cnhNumber: '10987654321')))
        ->toThrow(DuplicateCpfException::class);

    expect($this->repository->employees)->toHaveCount(1);
});

it('rejects a duplicated license number', function (): void {
    $this->action->execute(driverData());

    expect(fn () => $this->action->execute(driverData(cpf: '11144477735')))
        ->toThrow(DuplicateCnhException::class);
});

it('rejects a driver without complete license data', function (?string $number, ?string $category, ?string $expiration): void {
    expect(fn () => $this->action->execute(driverData(cnhNumber: $number, cnhCategory: $category, cnhExpiration: $expiration)))
        ->toThrow(DriverLicenseRequiredException::class);

    expect($this->repository->employees)->toBeEmpty();
})->with([
    'no number' => [null, 'D', '2027-06-30'],
    'no category' => ['12345678901', null, '2027-06-30'],
    'no expiration' => ['12345678901', 'D', null],
]);

it('rejects a license that is expired or expires today', function (string $expiration): void {
    expect(fn () => $this->action->execute(driverData(cnhExpiration: $expiration)))
        ->toThrow(ExpiredDriverLicenseException::class);
})->with(['2026-11-12', '2026-11-13']);

it('accepts a license that expires tomorrow', function (): void {
    expect($this->action->execute(driverData(cnhExpiration: '2026-11-14'))->cnhExpirationDate?->format('Y-m-d'))
        ->toBe('2026-11-14');
});
