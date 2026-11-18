<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Contracts\TokenService;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\CannotDeactivateSelfException;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\UserDeactivated;

/** Tira o acesso sem apagar o usuário: o histórico e a auditoria continuam apontando para ele. */
final readonly class DeactivateUserAction
{
    public function __construct(
        private UserRepository $users,
        private TokenService $tokens,
        private TransactionRunner $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function execute(string $userId, string $actorId): User
    {
        return $this->transactions->run(function () use ($userId, $actorId): User {
            $user = $this->users->lockForUpdate($userId) ?? throw new UserNotFoundException($userId);

            if ($user->id === $actorId) {
                throw new CannotDeactivateSelfException;
            }

            if (! $user->isActive()) {
                return $user;
            }

            if ($user->isActiveAdministrator() && $this->users->lockActiveAdministrators() <= 1) {
                throw new LastAdministratorException('user');
            }

            $this->users->changeDeactivation($user->id, $this->clock->now());
            $this->tokens->revokeAllFor($user->id);
            $this->events->dispatch(new UserDeactivated($user->id, $user->email));

            return $this->users->findById($user->id) ?? $user;
        });
    }
}
