<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Identity;

use App\Domain\Identity\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuário na gestão de acessos; o hash da senha nunca sai.
 *
 * @property-read User $resource
 */
final class UserResource extends JsonResource
{
    public function __construct(User $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, name: string, email: string, role: string, role_label: string, active: bool, deactivated_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $this->resource->role->value,
            'role_label' => $this->resource->role->label(),
            'active' => $this->resource->isActive(),
            'deactivated_at' => $this->resource->deactivatedAt?->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
        ];
    }
}
