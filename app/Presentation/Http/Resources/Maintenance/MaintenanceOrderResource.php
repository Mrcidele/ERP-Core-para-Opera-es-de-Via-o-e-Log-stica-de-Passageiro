<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Maintenance;

use App\Domain\Maintenance\Entities\MaintenanceOrder;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MaintenanceOrder $resource
 */
final class MaintenanceOrderResource extends JsonResource
{
    public function __construct(MaintenanceOrder $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: string, vehicle_id: string, mechanic_id: string, type: string, status: string, description: string, cost_cents: int, opened_at: string, completed_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $utc = new DateTimeZone('UTC');

        return [
            'id' => $this->resource->id,
            'vehicle_id' => $this->resource->vehicleId,
            'mechanic_id' => $this->resource->mechanicId,
            'type' => $this->resource->type->value,
            'status' => $this->resource->status->value,
            'description' => $this->resource->description,
            'cost_cents' => $this->resource->costCents,
            'opened_at' => $this->resource->openedAt->setTimezone($utc)->format(DATE_ATOM),
            'completed_at' => $this->resource->completedAt?->setTimezone($utc)->format(DATE_ATOM),
        ];
    }
}
