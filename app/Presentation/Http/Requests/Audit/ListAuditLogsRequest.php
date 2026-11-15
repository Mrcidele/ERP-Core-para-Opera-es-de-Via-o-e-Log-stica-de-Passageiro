<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Audit;

use App\Domain\Audit\Actions\ListAuditLogsAction;
use Illuminate\Foundation\Http\FormRequest;

final class ListAuditLogsRequest extends FormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.ListAuditLogsAction::MAX_LIMIT],
        ];
    }

    public function limit(): int
    {
        return $this->integer('limit', 100);
    }
}
