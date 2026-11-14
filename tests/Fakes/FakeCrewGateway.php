<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\Contracts\CrewGateway;

final class FakeCrewGateway implements CrewGateway
{
    /** @param  list<string>  $activeDriverIds */
    public function __construct(public array $activeDriverIds = []) {}

    public function isActiveDriver(string $employeeId): bool
    {
        return in_array($employeeId, $this->activeDriverIds, true);
    }
}
