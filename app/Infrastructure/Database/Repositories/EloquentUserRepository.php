<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repositories\UserRepository;
use App\Infrastructure\Database\Models\UserModel;
use Illuminate\Support\Str;

final class EloquentUserRepository implements UserRepository
{
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
