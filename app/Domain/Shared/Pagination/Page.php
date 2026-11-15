<?php

declare(strict_types=1);

namespace App\Domain\Shared\Pagination;

/**
 * Uma página de resultados e o total que existe com os mesmos filtros.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    /** Última página existente; 1 mesmo quando não há resultados. */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
