<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentUserRepository implements UserRepository
{
    private const UNIQUE_VIOLATION = '23505';

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

    public function changePasswordHash(string $userId, string $passwordHash): void
    {
        // Grava o hash como está: o cast `hashed` do model só confere que ele é do algoritmo atual.
        UserModel::query()->whereKey($userId)->update(['password' => $passwordHash]);
    }

    private function toEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            role: $model->role,
            passwordHash: $model->password,
        );
    }
}
