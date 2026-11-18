<?php

declare(strict_types=1);

namespace App\Domain\Identity\Repositories;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use DateTimeImmutable;

interface UserRepository
{
    public function nextIdentity(): string;

    /**
     * @throws EmailAlreadyTakenException quando outro cadastro simultâneo levou o mesmo e-mail
     */
    public function add(User $user): void;

    public function findById(string $id): ?User;

    /** A busca não diferencia maiúsculas de minúsculas. */
    public function findByEmail(string $email): ?User;

    /**
     * Usuários por nome; `search` procura em nome e e-mail, `status` é `active` ou `inactive`.
     *
     * @return Page<User>
     */
    public function paginate(PageQuery $query): Page;

    /** Trava o usuário (`SELECT ... FOR UPDATE`) até o fim da transação. */
    public function lockForUpdate(string $id): ?User;

    /**
     * Trava os administradores ativos e devolve quantos são: quem rebaixa ou desativa um deles
     * decide com a contagem estável até o fim da transação.
     */
    public function lockActiveAdministrators(): int;

    /**
     * Grava nome, e-mail e papel.
     *
     * @throws EmailAlreadyTakenException
     * @throws LastAdministratorException quando o banco recusa ficar sem administrador ativo
     */
    public function update(User $user): void;

    /**
     * @throws LastAdministratorException
     */
    public function changeDeactivation(string $userId, ?DateTimeImmutable $deactivatedAt): void;

    public function changePasswordHash(string $userId, string $passwordHash): void;
}
