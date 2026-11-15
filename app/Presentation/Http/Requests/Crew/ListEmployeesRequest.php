<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Crew;

use App\Domain\Crew\Enums\EmployeeStatus;
use App\Presentation\Http\Requests\ListRequest;

final class ListEmployeesRequest extends ListRequest
{
    protected function statuses(): array
    {
        return array_column(EmployeeStatus::cases(), 'value');
    }
}
