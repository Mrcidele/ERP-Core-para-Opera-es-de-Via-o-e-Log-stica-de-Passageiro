<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Crew\Entities\Employee;
use App\Domain\Crew\Repositories\EmployeeRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\EmployeeModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EloquentEmployeeRepository implements EmployeeRepository
{
    use PaginatesQueries;

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function paginate(PageQuery $query): Page
    {
        $builder = EmployeeModel::query()
            ->when($query->status !== null, fn (Builder $employees) => $employees->where('status', $query->status))
            ->when($query->search !== null, function (Builder $employees) use ($query): void {
                $digits = (string) preg_replace('/\D/', '', (string) $query->search);

                $employees->where(function (Builder $match) use ($query, $digits): void {
                    $match->where('name', 'ilike', $this->likeTerm((string) $query->search));

                    // O CPF é gravado só com dígitos; a busca aceita com ou sem máscara.
                    if ($digits !== '') {
                        $match->orWhere('cpf', 'like', $this->likeTerm($digits));
                    }
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        return $this->paginateQuery($builder, $query, fn (EmployeeModel $model): Employee => new Employee(
            id: $model->id,
            name: $model->name,
            cpf: $model->cpf,
            role: $model->role,
            status: $model->status,
            cnhNumber: $model->cnh_number,
            cnhCategory: $model->cnh_category,
            cnhExpirationDate: $model->cnh_expiration_date?->toDateTimeImmutable(),
        ));
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
