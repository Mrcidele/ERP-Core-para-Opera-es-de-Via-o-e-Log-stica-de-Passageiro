<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

final readonly class UserUpdated
{
    /**
     * @param  array<string, string>  $before  só os campos que mudaram (nome, e-mail, papel)
     * @param  array<string, string>  $after
     */
    public function __construct(
        public string $userId,
        public array $before,
        public array $after,
        /** A senha foi redefinida pelo administrador (o valor nunca trafega no evento). */
        public bool $passwordReset,
    ) {}
}
