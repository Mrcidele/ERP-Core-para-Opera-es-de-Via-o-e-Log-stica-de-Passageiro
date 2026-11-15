<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Maintenance;

use App\Domain\Maintenance\DTOs\MechanicDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MechanicDTO $resource
 */
final class MechanicResource extends JsonResource
{
    public function __construct(MechanicDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
