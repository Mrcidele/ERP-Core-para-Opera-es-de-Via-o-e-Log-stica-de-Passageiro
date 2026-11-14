<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Maintenance;

use App\Domain\Maintenance\DTOs\CompleteMaintenanceOrderDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Encerra uma ordem: `status` diz se ela foi concluída (com o custo final) ou cancelada. */
final class CloseMaintenanceOrderRequest extends FormRequest
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
            'status' => ['required', Rule::in(['completed', 'canceled'])],
            'cost_cents' => ['required_if:status,completed', 'nullable', 'integer', 'min:0', 'max:2000000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cost_cents.required_if' => 'Informe o custo final para concluir a ordem.',
        ];
    }

    public function isCompletion(): bool
    {
        return $this->string('status')->toString() === 'completed';
    }

    public function toCompletionDTO(string $orderId): CompleteMaintenanceOrderDTO
    {
        return new CompleteMaintenanceOrderDTO(
            orderId: $orderId,
            costCents: $this->integer('cost_cents'),
        );
    }
}
