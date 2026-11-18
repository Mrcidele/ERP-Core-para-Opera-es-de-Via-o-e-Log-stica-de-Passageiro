<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Contracts;

use App\Domain\Maintenance\DTOs\MechanicDTO;

/** O que Maintenance precisa da Equipe, sem depender das classes do módulo Crew. */
interface CrewGateway
{
    /** O funcionário existe, não foi excluído, é mecânico e está com status ativo? */
    public function isActiveMechanic(string $employeeId): bool;

    /**
     * Mecânicos ativos, por nome.
     *
     * @return list<MechanicDTO>
     */
    public function activeMechanics(): array;
}
