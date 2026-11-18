<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Identity;

use App\Domain\Identity\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Regras de formato do cadastro de usuário, as mesmas na tela e no comando `erp:create-user`. */
final class UserRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function profile(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Sem caracteres de controle: nada de quebra de linha escondida no e-mail.
            'email' => ['required', 'string', 'max:254', 'not_regex:/[\x00-\x1F\x7F]/', 'email:rfc'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function password(): array
    {
        return ['string', 'max:128', Password::min(12)->letters()->mixedCase()->numbers()];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil', 'password' => 'senha'];
    }
}
