<?php

declare(strict_types=1);

namespace App\Domain\Shared\Pagination;

/** Pedido de uma página de listagem, com os filtros opcionais de busca e status. */
final readonly class PageQuery
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public int $page;

    public int $perPage;

    public ?string $search;

    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        public ?string $status = null,
    ) {
        $this->page = max(1, $page);
        $this->perPage = max(1, min($perPage, self::MAX_PER_PAGE));
        $this->search = $search === null || trim($search) === '' ? null : trim($search);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
