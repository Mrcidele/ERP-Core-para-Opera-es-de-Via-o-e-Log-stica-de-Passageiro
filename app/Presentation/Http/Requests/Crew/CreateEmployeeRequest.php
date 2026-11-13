<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Crew;

use App\Domain\Crew\DTOs\CreateEmployeeDTO;
use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use App\Presentation\Http\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateEmployeeRequest extends FormRequest
{
    public const CNH_CATEGORIES = ['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => $this->digits($this->input('cpf')),
            'cnh_number' => $this->digits($this->input('cnh_number')),
            'cnh_category' => is_string($this->input('cnh_category'))
                ? strtoupper(trim($this->input('cnh_category')))
                : $this->input('cnh_category'),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'cpf' => ['required', 'string', new Cpf, Rule::unique('employees', 'cpf')->whereNull('deleted_at')],
            'role' => ['required', Rule::enum(EmployeeRole::class)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
            'cnh_number' => [
                'required_if:role,driver',
                'nullable',
                'string',
                'regex:/^\d{11}$/',
                Rule::unique('employees', 'cnh_number')->whereNull('deleted_at'),
            ],
            'cnh_category' => ['required_if:role,driver', 'nullable', 'string', Rule::in(self::CNH_CATEGORIES)],
            'cnh_expiration_date' => ['required_if:role,driver', 'nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cpf.unique' => 'Já existe um funcionário cadastrado com este CPF.',
            'cnh_number.unique' => 'Já existe um funcionário cadastrado com esta CNH.',
            'cnh_number.regex' => 'O número da CNH deve ter 11 dígitos.',
            'cnh_number.required_if' => 'Informe o número da CNH do motorista.',
            'cnh_category.required_if' => 'Informe a categoria da CNH do motorista.',
            'cnh_expiration_date.required_if' => 'Informe a validade da CNH do motorista.',
            'cnh_expiration_date.after' => 'A CNH está vencida ou vence hoje. Informe uma validade posterior a hoje.',
        ];
    }

    public function toDTO(): CreateEmployeeDTO
    {
        return new CreateEmployeeDTO(
            name: $this->string('name')->trim()->toString(),
            cpf: $this->string('cpf')->toString(),
            role: EmployeeRole::from($this->string('role')->toString()),
            status: EmployeeStatus::from($this->string('status')->toString()),
            cnhNumber: $this->filled('cnh_number') ? $this->string('cnh_number')->toString() : null,
            cnhCategory: $this->filled('cnh_category') ? $this->string('cnh_category')->toString() : null,
            cnhExpirationDate: $this->filled('cnh_expiration_date')
                ? $this->date('cnh_expiration_date', 'Y-m-d')?->startOfDay()->toDateTimeImmutable()
                : null,
        );
    }

    /** Mantém só os dígitos (remove a máscara); valores não textuais seguem para a validação. */
    private function digits(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return (string) preg_replace('/\D/', '', $value);
    }
}
