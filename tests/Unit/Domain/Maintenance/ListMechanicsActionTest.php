<?php

declare(strict_types=1);

use App\Domain\Maintenance\Actions\ListMechanicsAction;
use Tests\Fakes\FakeCrewGateway;

it('lists the mechanics who can take an order', function (): void {
    $mechanics = (new ListMechanicsAction(new FakeCrewGateway(activeDriverIds: ['driver-1'], activeMechanicIds: ['m1', 'm2'])))->execute();

    expect(array_map(fn ($mechanic): string => $mechanic->id, $mechanics))->toBe(['m1', 'm2']);
});
