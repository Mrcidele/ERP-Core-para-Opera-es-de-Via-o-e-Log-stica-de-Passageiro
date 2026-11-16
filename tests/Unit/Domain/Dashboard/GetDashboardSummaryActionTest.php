<?php

declare(strict_types=1);

use App\Domain\Dashboard\Actions\GetDashboardSummaryAction;
use App\Domain\Dashboard\DTOs\DashboardScopeDTO;
use App\Domain\Dashboard\DTOs\FleetSummaryDTO;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryDashboardReader;

beforeEach(function (): void {
    $this->reader = new InMemoryDashboardReader;
    $this->action = new GetDashboardSummaryAction($this->reader, new FixedClock('2026-11-13 10:00:00'));
});

it('builds every block with the right time windows', function (): void {
    $summary = $this->action->execute(new DashboardScopeDTO(true, true, true, true));

    expect($summary->generatedAt->format('Y-m-d H:i'))->toBe('2026-11-13 10:00')
        ->and($summary->fleet?->active)->toBe(8)
        ->and($summary->operations?->inProgressNow)->toBe(2)
        ->and($summary->crew?->expiringLicenses)->toBe([])
        ->and($summary->maintenance?->completedCostCents)->toBe(123_456)
        ->and($this->reader->calls)->toBe([
            'fleet' => [],
            // Próximas 24 horas, no máximo 5 viagens.
            'operations' => ['2026-11-13 10:00', '2026-11-14 10:00', 5],
            // CNH vencendo em até 30 dias, contados a partir de hoje.
            'crew' => ['2026-11-13 00:00', '2026-12-13 00:00'],
            // Ordens concluídas nos últimos 30 dias.
            'maintenance' => ['2026-10-14 10:00'],
            // Combustível do mês corrente, a partir do dia 1º.
            'fuel' => ['2026-11-01 00:00'],
        ])
        ->and($summary->fuel?->costCents)->toBe(987_654);
});

it('leaves out, without even querying, the blocks the user cannot see', function (DashboardScopeDTO $scope, array $queried): void {
    $summary = $this->action->execute($scope);

    expect(array_keys($this->reader->calls))->toBe($queried)
        ->and($summary->fleet !== null)->toBe($scope->fleet)
        ->and($summary->operations !== null)->toBe($scope->operations)
        ->and($summary->crew !== null)->toBe($scope->crew)
        ->and($summary->maintenance !== null)->toBe($scope->maintenance)
        // O combustível acompanha a permissão de consultar a frota.
        ->and($summary->fuel !== null)->toBe($scope->fleet);
})->with([
    'nothing' => [new DashboardScopeDTO(false, false, false, false), []],
    'workshop supervisor' => [new DashboardScopeDTO(true, false, false, true), ['fleet', 'maintenance', 'fuel']],
    'dispatcher' => [new DashboardScopeDTO(true, true, true, false), ['fleet', 'operations', 'crew', 'fuel']],
]);

it('computes the fleet total and availability rate', function (int $active, int $maintenance, int $inactive, int $total, float $rate): void {
    $fleet = new FleetSummaryDTO($active, $maintenance, $inactive, []);

    expect($fleet->total())->toBe($total)
        ->and($fleet->availabilityRate())->toBe($rate);
})->with([
    'usual fleet' => [8, 1, 1, 10, 80.0],
    'one decimal place' => [2, 1, 0, 3, 66.7],
    'everything running' => [5, 0, 0, 5, 100.0],
    'empty fleet' => [0, 0, 0, 0, 0.0],
]);
