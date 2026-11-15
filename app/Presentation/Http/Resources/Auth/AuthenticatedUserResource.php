<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Auth;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O usuário autenticado e o que ele pode fazer.
 *
 * @property-read User $resource
 */
final class AuthenticatedUserResource extends JsonResource
{
    public function __construct(User $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{user: array{id: string, name: string, email: string, role: string}, permissions: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'role' => $this->resource->role->value,
            ],
            'permissions' => array_map(
                fn (Permission $permission): string => $permission->value,
                $this->resource->role->permissions(),
            ),
        ];
    }
}
