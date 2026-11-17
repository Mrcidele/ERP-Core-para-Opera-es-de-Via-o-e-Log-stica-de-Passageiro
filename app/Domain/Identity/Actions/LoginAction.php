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
     * Hash Argon2id (mesmos custos de config/hashing.php) de uma senha aleatória descartada. Quando o
     * e-mail não tem conta, a senha é comparada com ele para a resposta levar o mesmo tempo e não
     * revelar quais e-mails existem.
     */
    private const UNKNOWN_USER_HASH = '$argon2id$v=19$m=19456,t=2,p=1$NEhHdnRaNmEveUJ4eHdUSQ$1BVmivL9sP0p3Jj9LDjL4UCN4x4ImmI+m4lhXwfQqHw';

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

        // Conta antiga (bcrypt) ou custos alterados: a senha acabou de ser conferida, então o hash é refeito agora.
        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->changePasswordHash($user->id, $this->hasher->hash($data->password));
        }

        return new AuthSessionDTO($user, $this->tokens->issueFor($user->id));
    }
}
