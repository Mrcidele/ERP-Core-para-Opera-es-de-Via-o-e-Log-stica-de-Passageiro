<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Auth;

use App\Domain\Identity\DTOs\AuthSessionDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AuthSessionDTO $resource
 */
final class AuthSessionResource extends JsonResource
{
    public function __construct(AuthSessionDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{token: string, user: array{id: string, name: string, email: string, role: string}, permissions: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            ...(new AuthenticatedUserResource($this->resource->user))->toArray($request),
        ];
    }
}
