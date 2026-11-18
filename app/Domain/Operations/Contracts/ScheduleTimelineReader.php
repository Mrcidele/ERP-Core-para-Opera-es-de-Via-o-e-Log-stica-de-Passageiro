<?php

declare(strict_types=1);

namespace App\Domain\Operations\Contracts;

use App\Domain\Operations\DTOs\ScheduleTimelineDTO;
use DateTimeImmutable;

/** Consulta de leitura da grade de escala, que cruza viagens com a frota, a equipe e a oficina. */
interface ScheduleTimelineReader
{
    /**
     * Veículos em operação (ativos, mais os que têm ordem de serviço pendente) e motoristas ativos, com o que
     * cada um tem entre $from e $to. Viagens canceladas ficam de fora. Para os motoristas contam também as
     * viagens cujo descanso de $restHours horas ainda alcança a janela.
     */
    public function read(DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): ScheduleTimelineDTO;
}
