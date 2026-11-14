<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Maintenance;

use App\Domain\Maintenance\DTOs\OpenMaintenanceOrderDTO;
use App\Domain\Maintenance\Enums\MaintenanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenMaintenanceOrderRequest extends FormRequest
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
            'vehicle_id' => ['required', 'uuid'],
            'mechanic_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function toDTO(): OpenMaintenanceOrderDTO
    {
        return new OpenMaintenanceOrderDTO(
            vehicleId: $this->string('vehicle_id')->toString(),
            mechanicId: $this->string('mechanic_id')->toString(),
            type: MaintenanceType::from($this->string('type')->toString()),
            description: $this->string('description')->trim()->toString(),
        );
    }
}
