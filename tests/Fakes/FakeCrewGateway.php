<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Maintenance\Contracts\CrewGateway as MaintenanceCrewGateway;
use App\Domain\Maintenance\DTOs\MechanicDTO;
use App\Domain\Operations\Contracts\CrewGateway as OperationsCrewGateway;
use DateTimeImmutable;
use DateTimeZone;

/** Tripulação em memória vista pelos módulos Operations e Maintenance. */
final class FakeCrewGateway implements MaintenanceCrewGateway, OperationsCrewGateway
{
    /**
     * @param  list<string>  $activeDriverIds
     * @param  array<string, string>  $licenseExpirations  id do motorista => validade (AAAA-MM-DD); quem não está aqui tem CNH sem vencimento próximo
     * @param  list<string>  $activeMechanicIds
     */
    public function __construct(
        public array $activeDriverIds = [],
        public array $licenseExpirations = [],
        public array $activeMechanicIds = [],
    ) {}

    public function isActiveDriver(string $employeeId): bool
    {
        return in_array($employeeId, $this->activeDriverIds, true);
    }

    public function isDriverLicenseValidUntil(string $employeeId, DateTimeImmutable $moment): bool
    {
        $expiration = $this->licenseExpirations[$employeeId] ?? '9999-12-31';

        return $expiration >= $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }

    public function isActiveMechanic(string $employeeId): bool
    {
        return in_array($employeeId, $this->activeMechanicIds, true);
    }

    public function activeMechanics(): array
    {
        return array_map(fn (string $id): MechanicDTO => new MechanicDTO($id, "Mecânico {$id}"), $this->activeMechanicIds);
    }
}
