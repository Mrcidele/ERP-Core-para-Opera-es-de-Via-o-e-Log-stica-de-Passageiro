<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final readonly class ListUsersAction
{
    public function __construct(
        private UserRepository $users,
    ) {}

    /**
     * @return Page<User>
     */
    public function execute(PageQuery $query = new PageQuery): Page
    {
        return $this->users->paginate($query);
    }
}
