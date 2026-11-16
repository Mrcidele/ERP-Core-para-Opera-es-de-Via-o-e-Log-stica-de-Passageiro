<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fuel;

use App\Domain\Fuel\DTOs\RecordFuelLogDTO;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class RecordFuelLogRequest extends FormRequest
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
            'odometer_km' => ['required', 'integer', 'min:0', 'max:2000000000'],
            // Mililitros: 350,5 L chegam como 350500. Teto de 5.000 L por abastecimento.
            'liters_milliliters' => ['required', 'integer', 'min:1', 'max:5000000'],
            'total_cost_cents' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'station_name' => ['required', 'string', 'max:120'],
            // ISO 8601 com fuso (ex.: 2026-11-16T14:30:00-03:00), para não haver ambiguidade de horário.
            'fueled_at' => ['required', 'date_format:'.DATE_ATOM.',Y-m-d\TH:i:s.v\Z'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'veículo',
            'odometer_km' => 'hodômetro',
            'liters_milliliters' => 'volume',
            'total_cost_cents' => 'valor total',
            'station_name' => 'posto',
            'fueled_at' => 'data do abastecimento',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['fueled_at.date_format' => 'Informe a data do abastecimento em ISO 8601 com fuso horário.'];
    }

    public function toDTO(): RecordFuelLogDTO
    {
        $userId = $this->user()?->getAuthIdentifier();

        return new RecordFuelLogDTO(
            vehicleId: $this->string('vehicle_id')->toString(),
            // A rota exige autenticação: quem registra é sempre o usuário do token, nunca um campo do formulário.
            recordedByUserId: is_string($userId) ? $userId : '',
            odometerKm: $this->integer('odometer_km'),
            litersMilliliters: $this->integer('liters_milliliters'),
            totalCostCents: $this->integer('total_cost_cents'),
            stationName: $this->string('station_name')->trim()->toString(),
            fueledAt: CarbonImmutable::parse($this->string('fueled_at')->toString())->utc(),
        );
    }
}
