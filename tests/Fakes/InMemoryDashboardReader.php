<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Dashboard\Contracts\DashboardReader;
use App\Domain\Dashboard\DTOs\CrewAlertsDTO;
use App\Domain\Dashboard\DTOs\FleetSummaryDTO;
use App\Domain\Dashboard\DTOs\MaintenanceSummaryDTO;
use App\Domain\Dashboard\DTOs\OperationsSummaryDTO;
use DateTimeImmutable;

/** Devolve blocos fixos e registra com que datas e limites cada consulta foi feita. */
final class InMemoryDashboardReader implements DashboardReader
{
    /** @var array<string, list<mixed>> */
    public array $calls = [];

    public function fleet(): FleetSummaryDTO
    {
        $this->calls['fleet'] = [];

        return new FleetSummaryDTO(8, 1, 1, []);
    }

    public function operations(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): OperationsSummaryDTO
    {
        $this->calls['operations'] = [$now->format('Y-m-d H:i'), $until->format('Y-m-d H:i'), $limit];

        return new OperationsSummaryDTO(2, 3, []);
    }

    public function crewAlerts(DateTimeImmutable $today, DateTimeImmutable $until): CrewAlertsDTO
    {
        $this->calls['crew'] = [$today->format('Y-m-d H:i'), $until->format('Y-m-d H:i')];

        return new CrewAlertsDTO([]);
    }

    public function maintenance(DateTimeImmutable $since): MaintenanceSummaryDTO
    {
        $this->calls['maintenance'] = [$since->format('Y-m-d H:i')];

        return new MaintenanceSummaryDTO(4, 123_456, 1, 0);
    }
}
