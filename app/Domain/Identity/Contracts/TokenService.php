<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

interface TokenService
{
    /** Revoga os tokens anteriores do usuário e emite um novo; devolve o token em texto puro. */
    public function issueFor(string $userId): string;

    /** Revoga o token informado (o texto puro enviado no cabeçalho Authorization). */
    public function revoke(string $plainTextToken): void;

    /** Encerra todas as sessões do usuário (desativação, troca de senha pelo administrador). */
    public function revokeAllFor(string $userId): void;
}
