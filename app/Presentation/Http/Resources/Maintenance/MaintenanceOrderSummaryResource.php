<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Maintenance;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MaintenanceOrderSummaryDTO $resource
 */
final class MaintenanceOrderSummaryResource extends JsonResource
{
    public function __construct(MaintenanceOrderSummaryDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     status: string,
     *     description: string,
     *     cost_cents: int,
     *     opened_at: string,
     *     completed_at: string|null,
     *     estimated_completion_at: string|null,
     *     vehicle: array{id: string, plate: string},
     *     mechanic: array{id: string, name: string},
     * }
     */
    public function toArray(Request $request): array
    {
        $utc = new DateTimeZone('UTC');
        $order = $this->resource->order;

        return [
            'id' => $order->id,
            'type' => $order->type->value,
            'status' => $order->status->value,
            'description' => $order->description,
            'cost_cents' => $order->costCents,
            'opened_at' => $order->openedAt->setTimezone($utc)->format(DATE_ATOM),
            'completed_at' => $order->completedAt?->setTimezone($utc)->format(DATE_ATOM),
            'estimated_completion_at' => $order->estimatedCompletionAt?->setTimezone($utc)->format(DATE_ATOM),
            'vehicle' => [
                'id' => $order->vehicleId,
                'plate' => $this->resource->vehiclePlate,
            ],
            'mechanic' => [
                'id' => $order->mechanicId,
                'name' => $this->resource->mechanicName,
            ],
        ];
    }
}
