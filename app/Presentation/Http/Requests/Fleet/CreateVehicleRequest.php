<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\DTOs\CreateVehicleDTO;
use App\Domain\Fleet\Enums\VehicleClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateVehicleRequest extends FormRequest
{
    /** Placa no padrão antigo (ABC1234) ou Mercosul (ABC1D23). */
    public const PLATE_PATTERN = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

    /** Chassi (VIN): 17 caracteres, sem I, O e Q. */
    public const CHASSIS_PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate' => $this->normalize($this->input('plate')),
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
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'class_type' => ['required', Rule::enum(VehicleClass::class)],
            'current_mileage' => ['required', 'integer', 'min:0'],
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

    public function toDTO(): CreateVehicleDTO
    {
        return new CreateVehicleDTO(
            plate: $this->string('plate')->toString(),
            chassis: $this->string('chassis')->toString(),
            capacity: $this->integer('capacity'),
            classType: VehicleClass::from($this->string('class_type')->toString()),
            currentMileage: $this->integer('current_mileage'),
        );
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
