<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Regras de formato comuns ao cadastro e à edição de veículos. */
abstract class VehicleRequest extends FormRequest
{
    /** Placa no padrão antigo (ABC1234) ou Mercosul (ABC1D23). */
    public const PLATE_PATTERN = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    /** Chassi (VIN): 17 caracteres, sem I, O e Q. */
    public const CHASSIS_PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/';

    /** Carga útil máxima aceita, em quilos (composições como rodotrem ficam abaixo disso). */
    public const MAX_PAYLOAD_KG = 60_000;

    /** Faixa da meta de rendimento: de 0,5 a 30 km/L, a mesma da constraint no banco. */
    public const MIN_METERS_PER_LITER = 500;

    public const MAX_METERS_PER_LITER = 30_000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate' => $this->normalize($this->input('plate')),
            'model' => is_string($this->input('model')) ? trim($this->input('model')) : $this->input('model'),
            'chassis' => $this->normalize($this->input('chassis')),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'regex:'.self::PLATE_PATTERN],
            'chassis' => ['required', 'string', 'regex:'.self::CHASSIS_PATTERN],
            'payload_kg' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PAYLOAD_KG],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'model' => ['nullable', 'string', 'max:60'],
            'current_mileage' => ['required', 'integer', 'min:0'],
            'next_preventive_maintenance_km' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            // Meta de rendimento em metros por litro (2200 = 2,2 km/L); vazio segue o padrão do tipo.
            'min_meters_per_liter' => ['nullable', 'integer', 'min:'.self::MIN_METERS_PER_LITER, 'max:'.self::MAX_METERS_PER_LITER],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate.regex' => 'A placa deve estar no padrão ABC1234 ou ABC1D23.',
            'chassis.regex' => 'O chassi deve ter 17 caracteres alfanuméricos, sem as letras I, O e Q.',
        ];
    }

    /** Remove hífens/espaços e padroniza em maiúsculas; valores não textuais seguem para a validação. */
    private function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return strtoupper((string) preg_replace('/[\s\-]/', '', $value));
    }
}
