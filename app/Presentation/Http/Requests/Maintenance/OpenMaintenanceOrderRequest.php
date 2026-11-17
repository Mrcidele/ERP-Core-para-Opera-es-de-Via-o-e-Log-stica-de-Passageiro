<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Maintenance;

use App\Domain\Maintenance\DTOs\OpenMaintenanceOrderDTO;
use App\Domain\Maintenance\Enums\MaintenanceType;
use Carbon\CarbonImmutable;
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
            // ISO 8601 com fuso (ex.: 2026-11-20T18:00:00-03:00); gravado em UTC.
            'estimated_completion_at' => ['nullable', 'date_format:'.DATE_ATOM.',Y-m-d\TH:i:s.v\Z'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['estimated_completion_at.date_format' => 'Informe a previsão de término em ISO 8601 com fuso horário.'];
    }

    public function toDTO(): OpenMaintenanceOrderDTO
    {
        return new OpenMaintenanceOrderDTO(
            vehicleId: $this->string('vehicle_id')->toString(),
            mechanicId: $this->string('mechanic_id')->toString(),
            type: MaintenanceType::from($this->string('type')->toString()),
            description: $this->string('description')->trim()->toString(),
            estimatedCompletionAt: $this->filled('estimated_completion_at')
                ? CarbonImmutable::parse($this->string('estimated_completion_at')->toString())->utc()->toDateTimeImmutable()
                : null,
        );
    }
}
