<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use App\Domain\Dashboard\Contracts\DashboardReader;
use App\Domain\Dashboard\DTOs\DashboardScopeDTO;
use App\Domain\Dashboard\DTOs\DashboardSummaryDTO;
use App\Domain\Shared\Contracts\Clock;

final readonly class GetDashboardSummaryAction
{
    public const UPCOMING_WINDOW_HOURS = 24;

    public const NEXT_TRIPS_LIMIT = 5;

    public const LICENSE_ALERT_DAYS = 30;

    public const MAINTENANCE_PERIOD_DAYS = 30;

    public function __construct(
        private DashboardReader $reader,
        private Clock $clock,
    ) {}

    public function execute(DashboardScopeDTO $scope): DashboardSummaryDTO
    {
        $now = $this->clock->now();
        $today = $this->clock->today();

        return new DashboardSummaryDTO(
            generatedAt: $now,
            fleet: $scope->fleet ? $this->reader->fleet() : null,
            operations: $scope->operations
                ? $this->reader->operations($now, $now->modify('+'.self::UPCOMING_WINDOW_HOURS.' hours'), self::NEXT_TRIPS_LIMIT)
                : null,
            crew: $scope->crew
                ? $this->reader->crewAlerts($today, $today->modify('+'.self::LICENSE_ALERT_DAYS.' days'))
                : null,
            maintenance: $scope->maintenance
                ? $this->reader->maintenance($now->modify('-'.self::MAINTENANCE_PERIOD_DAYS.' days'))
                : null,
            // Mês corrente, a partir do dia 1º à meia-noite (UTC).
            fuel: $scope->fleet ? $this->reader->fuel($today->modify('first day of this month')) : null,
        );
    }
}
