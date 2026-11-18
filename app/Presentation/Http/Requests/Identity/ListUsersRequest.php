<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Identity;

use App\Presentation\Http\Requests\ListRequest;

final class ListUsersRequest extends ListRequest
{
    protected function statuses(): array
    {
        return ['active', 'inactive'];
    }
}
