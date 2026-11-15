<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Actions;

use App\Domain\Maintenance\Contracts\CrewGateway;
use App\Domain\Maintenance\DTOs\MechanicDTO;

/** Mecânicos que podem assumir uma ordem de serviço. */
final readonly class ListMechanicsAction
{
    public function __construct(
        private CrewGateway $crew,
    ) {}

    /**
     * @return list<MechanicDTO>
     */
    public function execute(): array
    {
        return $this->crew->activeMechanics();
    }
}
