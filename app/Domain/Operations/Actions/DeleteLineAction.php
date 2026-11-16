<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Exceptions\LineHasActiveTripsException;
use App\Domain\Operations\Exceptions\LineRecordNotFoundException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Contracts\TransactionRunner;
use App\Domain\Shared\Events\LineDeleted;

final readonly class DeleteLineAction
{
    public function __construct(
        private LineRepository $lines,
        private EventDispatcher $events,
        private TransactionRunner $transaction,
    ) {}

    public function execute(string $id): void
    {
        $this->transaction->run(function () use ($id): void {
            // Com a linha travada, nenhum agendamento nela é gravado entre a conferência das viagens e a exclusão.
            $line = $this->lines->lockActive($id) ?? throw new LineRecordNotFoundException($id);

            if ($this->lines->hasActiveTrips($id)) {
                throw new LineHasActiveTripsException;
            }

            $this->lines->delete($id);
            $this->events->dispatch(new LineDeleted($line->id, $line->snapshot()));
        });
    }
}
