<?php

declare(strict_types=1);

namespace App\Domain\Identity\DTOs;

use App\Domain\Identity\Enums\UserRole;
use SensitiveParameter;

final readonly class UpdateUserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public UserRole $role,
        /** Nova senha definida pelo administrador; nula mantém a atual. */
        #[SensitiveParameter]
        public ?string $password = null,
    ) {}
}
