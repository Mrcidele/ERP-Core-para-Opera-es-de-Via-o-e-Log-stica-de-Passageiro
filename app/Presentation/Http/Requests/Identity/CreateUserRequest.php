<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Identity;

use App\Domain\Identity\DTOs\CreateUserDTO;
use App\Domain\Identity\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserRequest extends FormRequest
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
            'password' => ['required', 'confirmed', ...UserRules::password()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return UserRules::attributes();
    }

    public function toDTO(): CreateUserDTO
    {
        return new CreateUserDTO(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            role: UserRole::from($this->string('role')->toString()),
            password: $this->string('password')->toString(),
        );
    }
}
