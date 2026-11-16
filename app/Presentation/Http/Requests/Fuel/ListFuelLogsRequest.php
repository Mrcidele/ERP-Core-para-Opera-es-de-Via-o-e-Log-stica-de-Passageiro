<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fuel;

use App\Presentation\Http\Requests\ListRequest;

final class ListFuelLogsRequest extends ListRequest
{
    protected function statuses(): array
    {
        return [];
    }
}
