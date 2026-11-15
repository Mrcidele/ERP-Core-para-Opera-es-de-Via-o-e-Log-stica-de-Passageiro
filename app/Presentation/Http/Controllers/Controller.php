<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Shared\Pagination\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * Coleção de uma página com os metadados no contrato do Laravel (`meta.current_page`, `meta.last_page`, `meta.total`).
     *
     * @template T
     *
     * @param  class-string<JsonResource>  $resource
     * @param  Page<T>  $page
     */
    protected function paginated(string $resource, Page $page): AnonymousResourceCollection
    {
        return $resource::collection($page->items)->additional([
            'meta' => [
                'current_page' => $page->page,
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage,
                'total' => $page->total,
            ],
        ]);
    }
}
