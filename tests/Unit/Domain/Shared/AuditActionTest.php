<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\AuditAction;

it('names every audited action in Portuguese', function (): void {
    expect(array_map(fn (AuditAction $action): array => [$action->value, $action->label()], AuditAction::cases()))->toBe([
        ['vehicle.deleted', 'Veículo excluído'],
        ['line.created', 'Linha criada'],
        ['line.updated', 'Linha alterada'],
        ['line.deleted', 'Linha excluída'],
        ['trip.scheduled', 'Viagem agendada'],
        ['trip.completed', 'Viagem concluída'],
        ['trip.canceled', 'Viagem cancelada'],
        ['maintenance_order.opened', 'Ordem de serviço aberta'],
        ['maintenance_order.completed', 'Ordem de serviço concluída'],
        ['fuel_log.recorded', 'Abastecimento registrado'],
        ['auth.login_failed', 'Login recusado'],
    ]);
});

it('labels a stored code and keeps an unknown one as it is', function (): void {
    expect(AuditAction::labelFor('trip.canceled'))->toBe('Viagem cancelada')
        ->and(AuditAction::labelFor('legacy.action'))->toBe('legacy.action');
});

it('finds the codes whose label contains the term, ignoring case and accents', function (string $term, array $codes): void {
    expect(AuditAction::codesMatchingLabel($term))->toBe($codes);
})->with([
    ['excluído', ['vehicle.deleted']],
    ['EXCLUIDO', ['vehicle.deleted']],
    ['exclu', ['vehicle.deleted', 'line.deleted']],
    ['  Veículo ', ['vehicle.deleted']],
    ['servico', ['maintenance_order.opened', 'maintenance_order.completed']],
    ['concluída', ['trip.completed', 'maintenance_order.completed']],
    ['login', ['auth.login_failed']],
    ['vehicle.deleted', []],
    ['nada parecido', []],
    ['', []],
    ['   ', []],
]);
