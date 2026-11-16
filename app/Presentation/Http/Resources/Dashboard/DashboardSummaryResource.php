<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Dashboard;

use App\Domain\Dashboard\DTOs\DashboardSummaryDTO;
use App\Domain\Dashboard\DTOs\ExpiringLicenseDTO;
use App\Domain\Dashboard\DTOs\UpcomingTripDTO;
use App\Domain\Dashboard\DTOs\VehicleDueDTO;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DashboardSummaryDTO $resource
 */
final class DashboardSummaryResource extends JsonResource
{
    public function __construct(DashboardSummaryDTO $resource)
    {
        parent::__construct($resource);
    }

    /**
     * Cada bloco vem null quando o usuário não tem a permissão de consulta do módulo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'generated_at' => $this->iso($summary->generatedAt),
            'fleet' => $summary->fleet === null ? null : [
                'total' => $summary->fleet->total(),
                'active' => $summary->fleet->active,
                'maintenance' => $summary->fleet->maintenance,
                'inactive' => $summary->fleet->inactive,
                'availability_rate' => $summary->fleet->availabilityRate(),
                'preventive_maintenance_due' => array_map(fn (VehicleDueDTO $vehicle): array => [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'current_mileage' => $vehicle->currentMileage,
                    'next_preventive_maintenance_km' => $vehicle->nextPreventiveMaintenanceKm,
                    'needs_preventive_maintenance' => true,
                ], $summary->fleet->preventiveMaintenanceDue),
            ],
            'operations' => $summary->operations === null ? null : [
                'in_progress_now' => $summary->operations->inProgressNow,
                'scheduled_next_24h' => $summary->operations->scheduledNext24Hours,
                'next_trips' => array_map(fn (UpcomingTripDTO $trip): array => [
                    'id' => $trip->id,
                    'departure_time' => $this->iso($trip->departureTime),
                    'arrival_time' => $this->iso($trip->arrivalTime),
                    'line' => ['origin' => $trip->lineOrigin, 'destination' => $trip->lineDestination],
                    'vehicle' => ['plate' => $trip->vehiclePlate],
                    'driver' => ['name' => $trip->driverName],
                ], $summary->operations->nextTrips),
            ],
            'crew' => $summary->crew === null ? null : [
                'expiring_licenses' => array_map(fn (ExpiringLicenseDTO $license): array => [
                    'employee_id' => $license->employeeId,
                    'name' => $license->name,
                    'cnh_expiration_date' => $license->expirationDate->format('Y-m-d'),
                    'days_remaining' => $license->daysRemaining,
                ], $summary->crew->expiringLicenses),
            ],
            'maintenance' => $summary->maintenance === null ? null : [
                'completed_last_30_days' => [
                    'count' => $summary->maintenance->completedCount,
                    'total_cost_cents' => $summary->maintenance->completedCostCents,
                ],
                'open' => $summary->maintenance->openCount,
                'in_progress' => $summary->maintenance->inProgressCount,
            ],
            'fuel' => $summary->fuel === null ? null : [
                'month_start' => $this->iso($summary->fuel->since),
                'month_cost_cents' => $summary->fuel->costCents,
                'month_liters_milliliters' => $summary->fuel->litersMilliliters,
                'month_logs' => $summary->fuel->logCount,
            ],
        ];
    }

    private function iso(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
