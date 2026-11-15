<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Operations;

use App\Domain\Operations\Enums\TripStatus;
use App\Presentation\Http\Requests\ListRequest;

final class ListTripsRequest extends ListRequest
{
    protected function statuses(): array
    {
        return array_column(TripStatus::cases(), 'value');
    }
}
