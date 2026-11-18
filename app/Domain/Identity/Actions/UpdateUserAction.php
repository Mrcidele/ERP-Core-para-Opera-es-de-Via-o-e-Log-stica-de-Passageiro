<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\TokenService;
use App\Domain\Identity\DTOs\UpdateUserDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\UserUpdated;

final readonly class UpdateUserAction
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private TokenService $tokens,
        private TransactionRunner $transactions,
        private EventDispatcher $events,
    ) {}

    public function execute(UpdateUserDTO $data): User
    {
        return $this->transactions->run(function () use ($data): User {
            $current = $this->users->lockForUpdate($data->id) ?? throw new UserNotFoundException($data->id);

            $email = mb_strtolower(trim($data->email));
            $owner = $this->users->findByEmail($email);
            if ($owner !== null && $owner->id !== $current->id) {
                throw new EmailAlreadyTakenException;
            }

            // Tirar o papel de administrador do último administrador ativo deixaria a empresa sem quem gerencie acessos.
            if ($current->isActiveAdministrator() && $data->role !== UserRole::Admin && $this->users->lockActiveAdministrators() <= 1) {
                throw new LastAdministratorException('role');
            }

            $updated = new User($current->id, trim($data->name), $email, $data->role, $current->passwordHash, $current->deactivatedAt);
            $this->users->update($updated);

            $passwordReset = $data->password !== null;
            if ($passwordReset) {
                $this->users->changePasswordHash($current->id, $this->hasher->hash($data->password));
            }

            // Papel ou senha trocados: as sessões abertas valem para o acesso antigo e são encerradas.
            if ($passwordReset || $updated->role !== $current->role) {
                $this->tokens->revokeAllFor($current->id);
            }

            [$before, $after] = $this->changes($current, $updated);
            if ($before !== [] || $passwordReset) {
                $this->events->dispatch(new UserUpdated($current->id, $before, $after, $passwordReset));
            }

            return $this->users->findById($current->id) ?? $updated;
        });
    }

    /**
     * @return array{array<string, string>, array<string, string>}
     */
    private function changes(User $old, User $new): array
    {
        $fields = [
            'name' => [$old->name, $new->name],
            'email' => [$old->email, $new->email],
            'role' => [$old->role->value, $new->role->value],
        ];

        $before = $after = [];
        foreach ($fields as $field => [$was, $is]) {
            if ($was !== $is) {
                $before[$field] = $was;
                $after[$field] = $is;
            }
        }

        return [$before, $after];
    }
}
