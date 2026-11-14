<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Operations;

use App\Domain\Operations\DTOs\ScheduleTripDTO;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class ScheduleTripRequest extends FormRequest
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
            'line_id' => ['required', 'uuid'],
            'vehicle_id' => ['required', 'uuid'],
            'driver_id' => ['required', 'uuid'],
            // ISO 8601 com fuso (ex.: 2026-11-20T08:00:00-03:00), para não haver ambiguidade de horário.
            'departure_time' => ['required', 'date_format:'.DATE_ATOM.',Y-m-d\TH:i:s.v\Z', 'after:now'],
            'arrival_time' => ['required', 'date_format:'.DATE_ATOM.',Y-m-d\TH:i:s.v\Z', 'after:departure_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departure_time.date_format' => 'Informe a partida em ISO 8601 com fuso horário.',
            'arrival_time.date_format' => 'Informe a chegada em ISO 8601 com fuso horário.',
            'departure_time.after' => 'A partida deve ser no futuro.',
            'arrival_time.after' => 'A chegada deve ser depois da partida.',
        ];
    }

    public function toDTO(): ScheduleTripDTO
    {
        return new ScheduleTripDTO(
            lineId: $this->string('line_id')->toString(),
            vehicleId: $this->string('vehicle_id')->toString(),
            driverId: $this->string('driver_id')->toString(),
            departureTime: $this->moment('departure_time'),
            arrivalTime: $this->moment('arrival_time'),
        );
    }

    private function moment(string $key): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string($key)->toString())->utc();
    }
}
