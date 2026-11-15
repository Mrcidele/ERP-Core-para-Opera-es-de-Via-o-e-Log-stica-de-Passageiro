<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Presentation\Http\Requests\ListRequest;

final class ListVehiclesRequest extends ListRequest
{
    protected function statuses(): array
    {
        return array_column(VehicleStatus::cases(), 'value');
    }
}
