<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Crew;

use App\Domain\Crew\Entities\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Employee $resource
 */
final class EmployeeResource extends JsonResource
{
    public function __construct(Employee $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, name: string, cpf: string, role: string, status: string, cnh_number: string|null, cnh_category: string|null, cnh_expiration_date: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'cpf' => $this->resource->cpf,
            'role' => $this->resource->role->value,
            'status' => $this->resource->status->value,
            'cnh_number' => $this->resource->cnhNumber,
            'cnh_category' => $this->resource->cnhCategory,
            'cnh_expiration_date' => $this->resource->cnhExpirationDate?->format('Y-m-d'),
        ];
    }
}
