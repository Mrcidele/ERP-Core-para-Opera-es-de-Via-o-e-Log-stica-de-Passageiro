<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Identity;

use App\Domain\Identity\DTOs\UpdateUserDTO;
use App\Domain\Identity\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
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
            ...UserRules::profile(),
            // Opcional: só quando o administrador redefine a senha do usuário.
            'password' => ['nullable', 'confirmed', ...UserRules::password()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return UserRules::attributes();
    }

    public function toDTO(string $id): UpdateUserDTO
    {
        return new UpdateUserDTO(
            id: $id,
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            role: UserRole::from($this->string('role')->toString()),
            password: $this->filled('password') ? $this->string('password')->toString() : null,
        );
    }
}
