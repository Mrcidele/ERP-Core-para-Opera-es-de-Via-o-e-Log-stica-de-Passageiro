<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Operations;

use App\Domain\Operations\DTOs\LineDataDTO;
use App\Domain\Operations\DTOs\LineStopDTO;
use Illuminate\Foundation\Http\FormRequest;

/** Cadastro de rota, na criação e na edição. A ordem do array `stops` define a sequência das paradas. */
final class SaveLineRequest extends FormRequest
{
    public const MAX_STOPS = 40;

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
            'origin' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'max:120'],
            'distance_km' => ['required', 'integer', 'min:1', 'max:10000'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'stops' => ['array', 'list', 'max:'.self::MAX_STOPS],
            'stops.*.city_name' => ['required', 'string', 'max:120'],
            'stops.*.cumulative_km' => ['required', 'integer', 'min:0', 'max:10000'],
            'stops.*.estimated_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'origin' => 'origem',
            'destination' => 'destino',
            'distance_km' => 'distância',
            'estimated_duration_minutes' => 'duração',
            'stops' => 'paradas',
            'stops.*.city_name' => 'cidade da parada',
            'stops.*.cumulative_km' => 'km acumulado',
            'stops.*.estimated_minutes' => 'tempo até a parada',
        ];
    }

    public function toDTO(): LineDataDTO
    {
        $stops = [];

        foreach ($this->array('stops') as $index => $stop) {
            $stops[] = new LineStopDTO(
                cityName: trim($this->string("stops.{$index}.city_name")->toString()),
                cumulativeKm: $this->integer("stops.{$index}.cumulative_km"),
                estimatedMinutes: $this->integer("stops.{$index}.estimated_minutes"),
            );
        }

        return new LineDataDTO(
            origin: trim($this->string('origin')->toString()),
            destination: trim($this->string('destination')->toString()),
            distanceKm: $this->integer('distance_km'),
            estimatedDurationMinutes: $this->integer('estimated_duration_minutes'),
            stops: $stops,
        );
    }
}
