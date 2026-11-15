<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Contracts;

use App\Domain\Dashboard\DTOs\CrewAlertsDTO;
use App\Domain\Dashboard\DTOs\FleetSummaryDTO;
use App\Domain\Dashboard\DTOs\MaintenanceSummaryDTO;
use App\Domain\Dashboard\DTOs\OperationsSummaryDTO;
use DateTimeImmutable;

/** Consultas de leitura que o painel faz sobre os outros módulos, sem depender das classes deles. */
interface DashboardReader
{
    public function fleet(): FleetSummaryDTO;

    /** Viagens em andamento, agendadas entre $now e $until, e as $limit próximas a partir de $now. */
    public function operations(DateTimeImmutable $now, DateTimeImmutable $until, int $limit): OperationsSummaryDTO;

    /** Motoristas ativos cuja CNH vence até $until (inclui as já vencidas), contando os dias a partir de $today. */
    public function crewAlerts(DateTimeImmutable $today, DateTimeImmutable $until): CrewAlertsDTO;

    /** Ordens concluídas desde $since e ordens pendentes agora. */
    public function maintenance(DateTimeImmutable $since): MaintenanceSummaryDTO;
}
