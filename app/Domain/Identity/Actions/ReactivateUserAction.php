<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\UserReactivated;

final readonly class ReactivateUserAction
{
    public function __construct(
        private UserRepository $users,
        private TransactionRunner $transactions,
        private EventDispatcher $events,
    ) {}

    public function execute(string $userId): User
    {
        return $this->transactions->run(function () use ($userId): User {
            $user = $this->users->lockForUpdate($userId) ?? throw new UserNotFoundException($userId);

            if ($user->isActive()) {
                return $user;
            }

            $this->users->changeDeactivation($user->id, null);
            $this->events->dispatch(new UserReactivated($user->id, $user->email));

            return $this->users->findById($user->id) ?? $user;
        });
    }
}
