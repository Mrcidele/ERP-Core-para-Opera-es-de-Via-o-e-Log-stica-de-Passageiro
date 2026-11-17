<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use App\Domain\Fuel\Entities\FuelLog;
use App\Domain\Fuel\Repositories\FuelLogRepository;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

final class InMemoryFuelLogRepository implements FuelLogRepository
{
    use SlicesPages;

    /** @var list<FuelLog> na ordem em que foram gravados */
    public array $logs = [];

    /** @var list<string> ids travados, na ordem */
    public array $locked = [];

    /** @var list<string> ids excluídos, na ordem */
    public array $deleted = [];

    public function nextIdentity(): string
    {
        return 'fuel-'.(count($this->logs) + 1);
    }

    public function save(FuelLog $log): void
    {
        $this->logs[] = $log;
    }

    public function lockActive(string $id): ?FuelLog
    {
        $this->locked[] = $id;

        foreach ($this->logs as $log) {
            if ($log->id === $id) {
                return $log;
            }
        }

        return null;
    }

    public function delete(string $id): void
    {
        $this->logs = array_values(array_filter($this->logs, fn (FuelLog $log): bool => $log->id !== $id));
        $this->deleted[] = $id;
    }

    public function latestFor(string $vehicleId): ?FuelLog
    {
        $ofVehicle = $this->chronological($vehicleId);

        return $ofVehicle === [] ? null : $ofVehicle[count($ofVehicle) - 1];
    }

    public function findWithDetails(string $id): ?FuelLogSummaryDTO
    {
        foreach ($this->logs as $log) {
            if ($log->id === $id) {
                return $this->summary($log);
            }
        }

        return null;
    }

    public function paginateWithDetails(PageQuery $query): Page
    {
        $logs = array_values(array_filter(
            $this->logs,
            fn (FuelLog $log): bool => $query->search === null || str_contains(mb_strtolower($log->stationName), mb_strtolower($query->search)),
        ));

        usort($logs, fn (FuelLog $a, FuelLog $b): int => $b->fueledAt <=> $a->fueledAt);

        return $this->pageOf(array_map($this->summary(...), $logs), $query);
    }

    private function summary(FuelLog $log): FuelLogSummaryDTO
    {
        $previous = null;

        foreach ($this->chronological($log->vehicleId) as $candidate) {
            if ($candidate->id === $log->id) {
                break;
            }

            $previous = $candidate;
        }

        return new FuelLogSummaryDTO($log, 'AAA0A00', 'Usuário', $previous?->odometerKm);
    }

    /**
     * @return list<FuelLog>
     */
    private function chronological(string $vehicleId): array
    {
        $logs = array_values(array_filter($this->logs, fn (FuelLog $log): bool => $log->vehicleId === $vehicleId));

        usort($logs, fn (FuelLog $a, FuelLog $b): int => $a->fueledAt <=> $b->fueledAt);

        return $logs;
    }
}
