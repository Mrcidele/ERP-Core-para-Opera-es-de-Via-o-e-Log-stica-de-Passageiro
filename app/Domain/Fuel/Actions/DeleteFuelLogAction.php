<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Actions;

use App\Domain\Fuel\Exceptions\FuelLogNotFoundException;
use App\Domain\Fuel\Repositories\FuelLogRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\FuelLogDeleted;

/**
 * Exclui (logicamente) um abastecimento lançado errado. O rendimento e as conferências de hodômetro e data
 * ignoram registros excluídos, então o lançamento correto pode ser feito em seguida.
 * A quilometragem do veículo não volta atrás: ela também é alimentada por viagens e pelo cadastro.
 */
final readonly class DeleteFuelLogAction
{
    public function __construct(
        private FuelLogRepository $logs,
        private EventDispatcher $events,
        private TransactionRunner $transaction,
    ) {}

    public function execute(string $id): void
    {
        $this->transaction->run(function () use ($id): void {
            // Com o registro travado, duas exclusões simultâneas não auditam a mesma exclusão duas vezes:
            // a segunda espera e já não o encontra.
            $log = $this->logs->lockActive($id) ?? throw new FuelLogNotFoundException($id);

            $this->logs->delete($id);
            $this->events->dispatch(new FuelLogDeleted($log->id, $log->vehicleId, $log->odometerKm, $log->litersMilliliters, $log->totalCostCents, $log->stationName, $log->fueledAt));
        });
    }
}
