<?php

declare(strict_types=1);

namespace App\Domain\Operations\Contracts;

/** O que Operations precisa saber da Tripulação, sem depender das classes do módulo Crew. */
interface CrewGateway
{
    /** O funcionário existe, não foi excluído, é motorista e está com status ativo? */
    public function isActiveDriver(string $employeeId): bool;
}
