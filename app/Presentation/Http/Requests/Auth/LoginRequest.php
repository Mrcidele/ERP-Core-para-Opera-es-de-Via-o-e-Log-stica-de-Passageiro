<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Auth;

use App\Domain\Identity\DTOs\LoginDTO;
use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Caracteres de controle (quebra de linha etc.) são recusados antes da regra de e-mail.
            'email' => ['required', 'string', 'max:254', 'not_regex:/[\x00-\x1F\x7F]/', 'email'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.not_regex' => 'Informe um e-mail válido.',
        ];
    }

    public function toDTO(): LoginDTO
    {
        return new LoginDTO(
            email: $this->string('email')->trim()->lower()->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
