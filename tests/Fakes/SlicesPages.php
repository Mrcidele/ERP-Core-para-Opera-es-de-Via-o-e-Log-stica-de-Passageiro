<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

/** Recorta em memória a página pedida, como o banco faz com OFFSET/LIMIT. */
trait SlicesPages
{
    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return Page<T>
     */
    private function pageOf(array $items, PageQuery $query): Page
    {
        return new Page(array_slice($items, $query->offset(), $query->perPage), count($items), $query->page, $query->perPage);
    }
}
