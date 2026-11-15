<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Concerns;

use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait PaginatesQueries
{
    /**
     * @template TModel of Model
     * @template TItem
     *
     * @param  Builder<TModel>  $builder
     * @param  Closure(TModel): TItem  $map
     * @return Page<TItem>
     */
    private function paginateQuery(Builder $builder, PageQuery $query, Closure $map): Page
    {
        $total = (clone $builder)->toBase()->getCountForPagination();

        $items = $builder->offset($query->offset())->limit($query->perPage)->get()->map($map)->all();

        return new Page(array_values($items), $total, $query->page, $query->perPage);
    }

    /** Termo de busca pronto para ILIKE, com os curingas do usuário neutralizados. */
    private function likeTerm(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }
}
