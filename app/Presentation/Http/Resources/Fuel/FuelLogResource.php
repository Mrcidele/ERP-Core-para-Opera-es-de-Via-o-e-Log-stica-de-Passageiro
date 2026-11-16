<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Fuel;

use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FuelLogSummaryDTO $resource
 */
final class FuelLogResource extends JsonResource
{
    public function __construct(FuelLogSummaryDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{
     *     id: string,
     *     odometer_km: int,
     *     liters_milliliters: int,
     *     total_cost_cents: int,
     *     station_name: string,
     *     fueled_at: string,
     *     distance_km: int|null,
     *     km_per_liter: float|null,
     *     low_efficiency_alert: bool,
     *     vehicle: array{id: string, plate: string},
     *     recorded_by: array{id: string, name: string},
     * }
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource->log;

        return [
            'id' => $log->id,
            'odometer_km' => $log->odometerKm,
            'liters_milliliters' => $log->litersMilliliters,
            'total_cost_cents' => $log->totalCostCents,
            'station_name' => $log->stationName,
            'fueled_at' => $log->fueledAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            // Rendimento do trecho desde o abastecimento anterior do mesmo veículo; null no primeiro.
            'distance_km' => $this->resource->distanceKm(),
            'km_per_liter' => $this->resource->kmPerLiter(),
            'low_efficiency_alert' => $this->resource->lowEfficiencyAlert(),
            'vehicle' => ['id' => $log->vehicleId, 'plate' => $this->resource->vehiclePlate],
            'recorded_by' => ['id' => $log->recordedByUserId, 'name' => $this->resource->recordedByName],
        ];
    }
}
