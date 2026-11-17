<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\DTOs\CreateUserDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\UserCreated;

/** Cria uma conta de acesso. Hoje só o comando `erp:create-user` usa: o sistema não tem cadastro de usuários pela tela. */
final readonly class CreateUserAction
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private EventDispatcher $events,
    ) {}

    public function execute(CreateUserDTO $data, string $channel): User
    {
        // E-mail gravado em minúsculas: o login já não diferencia, e o índice único passa a valer para qualquer grafia.
        $email = mb_strtolower(trim($data->email));

        if ($this->users->findByEmail($email) !== null) {
            throw new EmailAlreadyTakenException;
        }

        $user = new User(
            id: $this->users->nextIdentity(),
            name: trim($data->name),
            email: $email,
            role: $data->role,
            passwordHash: $this->hasher->hash($data->password),
        );

        $this->users->add($user);
        $this->events->dispatch(new UserCreated($user->id, $user->name, $user->email, $user->role->value, $channel));

        return $user;
    }
}
