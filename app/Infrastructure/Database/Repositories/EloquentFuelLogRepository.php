<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Repositories;

use App\Domain\Fleet\Enums\VehicleType;
use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use App\Domain\Fuel\Entities\FuelLog;
use App\Domain\Fuel\Repositories\FuelLogRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;
use App\Infrastructure\Database\Concerns\PaginatesQueries;
use App\Infrastructure\Database\Models\FuelLogModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentFuelLogRepository implements FuelLogRepository
{
    use PaginatesQueries;

    public function nextIdentity(): string
    {
        return Str::orderedUuid()->toString();
    }

    public function save(FuelLog $log): void
    {
        FuelLogModel::query()->updateOrCreate(['id' => $log->id], [
            'vehicle_id' => $log->vehicleId,
            'recorded_by_user_id' => $log->recordedByUserId,
            'odometer_km' => $log->odometerKm,
            'liters_milliliters' => $log->litersMilliliters,
            'total_cost_cents' => $log->totalCostCents,
            'station_name' => $log->stationName,
            'fueled_at' => $log->fueledAt,
        ]);
    }

    public function lockActive(string $id): ?FuelLog
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = FuelLogModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function delete(string $id): void
    {
        FuelLogModel::query()->whereKey($id)->delete();
    }

    public function latestFor(string $vehicleId): ?FuelLog
    {
        if (! Str::isUuid($vehicleId)) {
            return null;
        }

        $model = FuelLogModel::query()->where('vehicle_id', $vehicleId)->orderByDesc('fueled_at')->orderByDesc('id')->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findWithDetails(string $id): ?FuelLogSummaryDTO
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $model = $this->withDetails()->whereKey($id)->first();

        return $model === null ? null : $this->toSummary($model);
    }

    public function paginateWithDetails(PageQuery $query): Page
    {
        $builder = $this->withDetails()
            ->when($query->search !== null, function (Builder $logs) use ($query): void {
                $term = $this->likeTerm((string) $query->search);
                // A placa é gravada em maiúsculas, sem hífen nem espaço.
                $plate = $this->likeTerm((string) preg_replace('/[\s\-]/', '', (string) $query->search));

                $logs->where(fn (Builder $match) => $match
                    ->where('station_name', 'ilike', $term)
                    ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('plate', 'ilike', $plate)));
            })
            ->orderByDesc('fueled_at')
            ->orderByDesc('id');

        return $this->paginateQuery($builder, $query, $this->toSummary(...));
    }

    /**
     * Cada abastecimento com o hodômetro do anterior do mesmo veículo, que é a base do rendimento.
     * O índice (vehicle_id, fueled_at) resolve a subconsulta.
     *
     * @return Builder<FuelLogModel>
     */
    private function withDetails(): Builder
    {
        $previous = DB::table('fuel_logs as previous')
            ->select('previous.odometer_km')
            ->whereColumn('previous.vehicle_id', 'fuel_logs.vehicle_id')
            ->whereNull('previous.deleted_at')
            ->whereRaw('(previous.fueled_at, previous.id) < (fuel_logs.fueled_at, fuel_logs.id)')
            ->orderByDesc('previous.fueled_at')
            ->orderByDesc('previous.id')
            ->limit(1);

        return FuelLogModel::query()
            ->select('fuel_logs.*')
            ->selectSub($previous, 'previous_odometer_km')
            ->with(['vehicle', 'recordedBy']);
    }

    private function toSummary(FuelLogModel $model): FuelLogSummaryDTO
    {
        return new FuelLogSummaryDTO(
            log: $this->toEntity($model),
            vehiclePlate: $model->vehicle->plate ?? '',
            recordedByName: $model->recordedBy->name ?? '',
            previousOdometerKm: $model->previous_odometer_km,
            minMetersPerLiter: $this->minMetersPerLiter($model),
        );
    }

    /** A meta própria do veículo ou, sem ela, o padrão do tipo de caminhão. */
    private function minMetersPerLiter(FuelLogModel $model): int
    {
        $vehicle = $model->vehicle;

        if ($vehicle === null) {
            return VehicleType::Truck->defaultMinMetersPerLiter();
        }

        return $vehicle->min_meters_per_liter ?? $vehicle->vehicle_type->defaultMinMetersPerLiter();
    }

    private function toEntity(FuelLogModel $model): FuelLog
    {
        return new FuelLog(
            id: $model->id,
            vehicleId: $model->vehicle_id,
            recordedByUserId: $model->recorded_by_user_id,
            odometerKm: $model->odometer_km,
            litersMilliliters: $model->liters_milliliters,
            totalCostCents: $model->total_cost_cents,
            stationName: $model->station_name,
            fueledAt: $model->fueled_at,
        );
    }
}
