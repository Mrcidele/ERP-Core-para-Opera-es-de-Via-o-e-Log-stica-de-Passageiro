<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

final class ScheduleTimelineRequest extends FormRequest
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
        return ['date' => ['required', 'date_format:Y-m-d']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['date.date_format' => 'Informe a data no formato AAAA-MM-DD.'];
    }

    /** Meia-noite (UTC) do dia pedido. */
    public function day(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->string('date')->toString().' 00:00:00', new DateTimeZone('UTC'));
    }
}
