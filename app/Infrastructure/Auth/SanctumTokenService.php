<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Identity\Contracts\TokenService;
use App\Infrastructure\Database\Models\UserModel;
use Laravel\Sanctum\PersonalAccessToken;

final class SanctumTokenService implements TokenService
{
    public function issueFor(string $userId): string
    {
        $user = UserModel::query()->findOrFail($userId);

        // Uma sessão por usuário: entrar de novo encerra as anteriores.
        $user->tokens()->delete();

        return $user->createToken('api')->plainTextToken;
    }

    public function revoke(string $plainTextToken): void
    {
        PersonalAccessToken::findToken($plainTextToken)?->delete();
    }
}
