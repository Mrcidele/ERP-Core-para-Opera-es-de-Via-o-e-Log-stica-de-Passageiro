<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Shared\Pagination\PageQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parâmetros comuns das listagens: `page`, `per_page`, `search` e `status`. */
abstract class ListRequest extends FormRequest
{
    /**
     * Valores de status que a listagem aceita como filtro; vazio quando ela não filtra por status.
     *
     * @return list<string>
     */
    abstract protected function statuses(): array;

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
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.PageQuery::MAX_PER_PAGE],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => $this->statuses() === [] ? ['prohibited'] : ['sometimes', 'nullable', 'string', Rule::in($this->statuses())],
        ];
    }

    public function toPageQuery(): PageQuery
    {
        return new PageQuery(
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', PageQuery::DEFAULT_PER_PAGE),
            search: $this->filled('search') ? $this->string('search')->toString() : null,
            status: $this->filled('status') ? $this->string('status')->toString() : null,
        );
    }
}
