<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\TokenService;
use App\Domain\Identity\DTOs\AuthSessionDTO;
use App\Domain\Identity\DTOs\LoginDTO;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\LoginFailed;

final readonly class LoginAction
{
    /**
     * Hash de uma senha aleatória descartada. Quando o e-mail não tem conta, a senha é comparada
     * com ele para a resposta levar o mesmo tempo e não revelar quais e-mails existem.
     */
    private const UNKNOWN_USER_HASH = '$2y$12$pl.ou64sTjEteb6BMqNneeMTeVJ400ZL8QBrUm5jp6e3IPH6kV2Sa';

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenService $tokens,
        private EventDispatcher $events,
    ) {}

    public function execute(LoginDTO $data): AuthSessionDTO
    {
        $user = $this->users->findByEmail($data->email);

        $passwordMatches = $this->hasher->verify($data->password, $user->passwordHash ?? self::UNKNOWN_USER_HASH);

        if ($user === null || ! $passwordMatches) {
            $this->events->dispatch(new LoginFailed($data->email, $user?->id));

            throw new InvalidCredentialsException;
        }

        return new AuthSessionDTO($user, $this->tokens->issueFor($user->id));
    }
}
