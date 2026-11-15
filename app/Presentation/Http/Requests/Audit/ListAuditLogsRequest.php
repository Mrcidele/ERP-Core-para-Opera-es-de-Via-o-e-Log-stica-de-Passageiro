<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Audit;

use App\Presentation\Http\Requests\ListRequest;

final class ListAuditLogsRequest extends ListRequest
{
    protected function statuses(): array
    {
        return [];
    }
}
