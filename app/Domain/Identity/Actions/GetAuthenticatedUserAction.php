<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepository;

final readonly class GetAuthenticatedUserAction
{
    public function __construct(
        private UserRepository $users,
    ) {}

    public function execute(string $userId): User
    {
        return $this->users->findById($userId) ?? throw new UserNotFoundException($userId);
    }
}
