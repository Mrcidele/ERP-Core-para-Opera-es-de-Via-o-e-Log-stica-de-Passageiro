<?php

declare(strict_types=1);

namespace App\Domain\Operations\Contracts;

use DateTimeImmutable;

/** O que Operations precisa saber da Equipe, sem depender das classes do módulo Crew. */
interface CrewGateway
{
    /** O funcionário existe, não foi excluído, é motorista e está com status ativo? */
    public function isActiveDriver(string $employeeId): bool;

    /** A CNH do motorista vale pelo menos até a data (em UTC) do momento informado? */
    public function isDriverLicenseValidUntil(string $employeeId, DateTimeImmutable $moment): bool;
}
