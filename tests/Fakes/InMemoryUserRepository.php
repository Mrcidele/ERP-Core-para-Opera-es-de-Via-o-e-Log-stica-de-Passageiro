<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use DateTimeImmutable;

final class InMemoryUserRepository implements UserRepository
{
    use SlicesPages;

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

    public function paginate(PageQuery $query): Page
    {
        $users = array_values(array_filter(
            $this->users,
            fn (User $user): bool => ($query->status === null || ($query->status === 'active') === $user->isActive())
                && ($query->search === null || str_contains(mb_strtolower($user->name.' '.$user->email), mb_strtolower($query->search))),
        ));

        usort($users, fn (User $a, User $b): int => $a->name <=> $b->name);

        return $this->pageOf($users, $query);
    }

    public function lockForUpdate(string $id): ?User
    {
        return $this->findById($id);
    }

    public function lockActiveAdministrators(): int
    {
        return count(array_filter($this->users, fn (User $user): bool => $user->isActiveAdministrator()));
    }

    public function update(User $user): void
    {
        $this->replace($user, 'role');
    }

    public function changeDeactivation(string $userId, ?DateTimeImmutable $deactivatedAt): void
    {
        $user = $this->users[$userId];
        $this->replace(new User($user->id, $user->name, $user->email, $user->role, $user->passwordHash, $deactivatedAt), 'user');
    }

    public function changePasswordHash(string $userId, string $passwordHash): void
    {
        $user = $this->users[$userId];
        $this->users[$userId] = new User($user->id, $user->name, $user->email, $user->role, $passwordHash, $user->deactivatedAt);
    }

    /** Mesma regra do trigger `users_keep_one_active_admin`: nunca zero administradores ativos. */
    private function replace(User $user, string $adminField): void
    {
        $previous = $this->users[$user->id];
        $this->users[$user->id] = $user;

        $remaining = array_filter($this->users, fn (User $candidate): bool => $candidate->isActiveAdministrator());
        if ($previous->isActiveAdministrator() && $remaining === []) {
            $this->users[$user->id] = $previous;

            throw new LastAdministratorException($adminField);
        }
    }
}
