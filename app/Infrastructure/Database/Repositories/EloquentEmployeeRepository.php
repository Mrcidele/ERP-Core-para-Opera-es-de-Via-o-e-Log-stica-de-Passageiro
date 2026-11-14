<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Infrastructure\Database\Models\EmployeeModel;
use Illuminate\Support\Str;

final class EloquentEmployeeRepository implements EmployeeRepository
{
    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function all(): array
    {
        return array_values(
            EmployeeModel::query()
                ->orderBy('name')
                ->get()
                ->map(fn (EmployeeModel $model): Employee => new Employee(
                    id: $model->id,
                    name: $model->name,
                    cpf: $model->cpf,
                    role: $model->role,
                    status: $model->status,
                    cnhNumber: $model->cnh_number,
                    cnhCategory: $model->cnh_category,
                    cnhExpirationDate: $model->cnh_expiration_date?->toDateTimeImmutable(),
                ))
                ->all(),
        );
    }

    public function existsByCpf(string $cpf): bool
    {
        return EmployeeModel::query()->where('cpf', $cpf)->exists();
    }

    public function existsByCnhNumber(string $cnhNumber): bool
    {
        return EmployeeModel::query()->where('cnh_number', $cnhNumber)->exists();
    }

    public function save(Employee $employee): void
    {
        EmployeeModel::query()->updateOrCreate(
            ['id' => $employee->id],
            [
                'name' => $employee->name,
                'cpf' => $employee->cpf,
                'role' => $employee->role,
                'status' => $employee->status,
                'cnh_number' => $employee->cnhNumber,
                'cnh_category' => $employee->cnhCategory,
                'cnh_expiration_date' => $employee->cnhExpirationDate?->format('Y-m-d'),
            ],
        );
    }
}
