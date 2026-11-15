<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Maintenance;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Presentation\Http\Requests\ListRequest;

final class ListMaintenanceOrdersRequest extends ListRequest
{
    protected function statuses(): array
    {
        return array_column(MaintenanceStatus::cases(), 'value');
    }
}
