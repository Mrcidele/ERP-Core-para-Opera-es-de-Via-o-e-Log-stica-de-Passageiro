<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentUserRepository implements UserRepository
{
    use PaginatesQueries;

    private const UNIQUE_VIOLATION = '23505';

    /** Erro levantado pelo trigger `users_keep_one_active_admin`. */
    private const LAST_ADMIN_MARKER = 'last_active_admin';

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function add(User $user): void
    {
        try {
            $model = new UserModel;
            $model->id = $user->id;
            $model->name = $user->name;
            $model->email = $user->email;
            $model->role = $user->role;
            // Já é o hash: o cast `hashed` do model reconhece e não aplica de novo.
            $model->password = $user->passwordHash;
            $model->save();
        } catch (QueryException $e) {
            // users_email_unique: outro cadastro levou o e-mail entre a conferência da Action e a gravação.
            if ($e->getCode() === self::UNIQUE_VIOLATION && str_contains($e->getMessage(), 'users_email_unique')) {
                throw new EmailAlreadyTakenException;
            }

            throw $e;
        }
    }

    public function findById(string $id): ?User
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = UserModel::query()->find($id);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function paginate(PageQuery $query): Page
    {
        $builder = UserModel::query()
            ->when($query->status === 'active', fn (Builder $users) => $users->whereNull('deactivated_at'))
            ->when($query->status === 'inactive', fn (Builder $users) => $users->whereNotNull('deactivated_at'))
            ->when($query->search !== null, function (Builder $users) use ($query): void {
                $term = $this->likeTerm((string) $query->search);
                $users->where(fn (Builder $match) => $match->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
            })
            ->orderBy('name')
            ->orderBy('id');

        return $this->paginateQuery($builder, $query, fn (UserModel $model): User => $this->toEntity($model));
    }

    public function lockForUpdate(string $id): ?User
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = UserModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function lockActiveAdministrators(): int
    {
        // As linhas são lidas de verdade: o PostgreSQL não aceita FOR UPDATE em um COUNT.
        $ids = UserModel::query()
            ->where('role', UserRole::Admin->value)
            ->whereNull('deactivated_at')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        return count($ids);
    }

    public function update(User $user): void
    {
        $this->guardingDatabaseRules(function () use ($user): void {
            UserModel::query()->whereKey($user->id)->update([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ]);
        }, 'role');
    }

    public function changeDeactivation(string $userId, ?DateTimeImmutable $deactivatedAt): void
    {
        $this->guardingDatabaseRules(function () use ($userId, $deactivatedAt): void {
            UserModel::query()->whereKey($userId)->update(['deactivated_at' => $deactivatedAt]);
        }, 'user');
    }

    public function changePasswordHash(string $userId, string $passwordHash): void
    {
        // Grava o hash como está: o cast `hashed` do model só confere que ele é do algoritmo atual.
        UserModel::query()->whereKey($userId)->update(['password' => $passwordHash]);
    }

    /**
     * Converte as violações das regras do banco (e-mail único, último administrador) nas exceções de domínio.
     *
     * @param  \Closure(): void  $write
     */
    private function guardingDatabaseRules(\Closure $write, string $adminField): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            if ($e->getCode() === self::UNIQUE_VIOLATION && str_contains($e->getMessage(), 'users_email_unique')) {
                throw new EmailAlreadyTakenException;
            }

            if (str_contains($e->getMessage(), self::LAST_ADMIN_MARKER)) {
                throw new LastAdministratorException($adminField);
            }

            throw $e;
        }
    }

    private function toEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            role: $model->role,
            passwordHash: $model->password,
            deactivatedAt: $model->deactivated_at?->toDateTimeImmutable(),
        );
    }
}
