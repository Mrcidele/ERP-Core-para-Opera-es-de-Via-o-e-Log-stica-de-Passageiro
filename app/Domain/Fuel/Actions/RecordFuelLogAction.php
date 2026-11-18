<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Actions;

use App\Domain\Fuel\Contracts\FleetGateway;
use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use App\Domain\Fuel\DTOs\RecordFuelLogDTO;
use App\Domain\Fuel\Entities\FuelLog;
use App\Domain\Fuel\Exceptions\FuelLogInTheFutureException;
use App\Domain\Fuel\Exceptions\FuelLogOutOfOrderException;
use App\Domain\Fuel\Exceptions\FuelVehicleNotFoundException;
use App\Domain\Fuel\Exceptions\OdometerRegressionException;
use App\Domain\Fuel\Repositories\FuelLogRepository;
use App\Domain\Shared\Contracts\Clock;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\FuelLogRecorded;
use RuntimeException;

final readonly class RecordFuelLogAction
{
    public function __construct(
        private FuelLogRepository $logs,
        private FleetGateway $fleet,
        private Clock $clock,
        private EventDispatcher $events,
        private TransactionRunner $transaction,
    ) {}

    public function execute(RecordFuelLogDTO $data): FuelLogSummaryDTO
    {
        if ($data->fueledAt > $this->clock->now()) {
            throw new FuelLogInTheFutureException;
        }

        return $this->transaction->run(function () use ($data): FuelLogSummaryDTO {
            // Com o veículo travado, o "último abastecimento" conferido abaixo ainda é o último quando este for gravado.
            $currentMileage = $this->fleet->lockVehicleMileage($data->vehicleId) ?? throw new FuelVehicleNotFoundException;
            $last = $this->logs->latestFor($data->vehicleId);

            if ($last !== null && $data->odometerKm < $last->odometerKm) {
                throw new OdometerRegressionException($last->odometerKm);
            }

            if ($last !== null && $data->fueledAt < $last->fueledAt) {
                throw new FuelLogOutOfOrderException;
            }

            $log = new FuelLog(
                id: $this->logs->nextIdentity(),
                vehicleId: $data->vehicleId,
                recordedByUserId: $data->recordedByUserId,
                odometerKm: $data->odometerKm,
                litersMilliliters: $data->litersMilliliters,
                totalCostCents: $data->totalCostCents,
                stationName: $data->stationName,
                fueledAt: $data->fueledAt,
            );

            $this->logs->save($log);

            // O hodômetro lido na bomba é mais novo que o cadastro: a Frota acompanha, sem nunca voltar atrás.
            if ($log->odometerKm > $currentMileage) {
                $this->fleet->raiseVehicleMileage($log->vehicleId, $log->odometerKm);
            }

            $summary = $this->logs->findWithDetails($log->id) ?? throw new RuntimeException('Abastecimento recém-gravado não encontrado.');
            $kmPerLiter = $summary->kmPerLiter();

            // O consumo abaixo da meta do veículo fica registrado na auditoria junto com o abastecimento.
            $this->events->dispatch(new FuelLogRecorded(
                $log->id,
                $log->vehicleId,
                $log->odometerKm,
                $log->litersMilliliters,
                $log->totalCostCents,
                $log->stationName,
                $log->fueledAt,
                metersPerLiter: $kmPerLiter === null ? null : (int) round($kmPerLiter * 1000),
                minMetersPerLiter: $summary->minMetersPerLiter,
                lowEfficiencyAlert: $summary->lowEfficiencyAlert(),
            ));

            return $summary;
        });
    }
}
