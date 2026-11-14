<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Contracts;

/** O que Maintenance precisa da Tripulação, sem depender das classes do módulo Crew. */
interface CrewGateway
{
    /** O funcionário existe, não foi excluído, é mecânico e está com status ativo? */
    public function isActiveMechanic(string $employeeId): bool;
}
