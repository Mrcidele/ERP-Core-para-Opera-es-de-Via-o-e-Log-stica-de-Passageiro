<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repositories\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    public array $users = [];

    public function nextIdentity(): string
    {
        return 'user-'.(count($this->users) + 1);
    }

    public function add(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        return array_find($this->users, fn (User $user): bool => strtolower($user->email) === strtolower($email));
    }
}
