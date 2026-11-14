<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\ListLinesAction;
use App\Domain\Operations\Entities\Line;
use Tests\Fakes\InMemoryLineRepository;

it('lists lines ordered by origin and destination', function (): void {
    $lines = new InMemoryLineRepository;
    $lines->add(new Line('2', 'Curitiba', 'Ourinhos', 400, 390));
    $lines->add(new Line('1', 'Curitiba', 'Londrina', 380, 360));

    $result = (new ListLinesAction($lines))->execute();

    expect(array_map(fn (Line $line): string => $line->destination, $result))->toBe(['Londrina', 'Ourinhos']);
});
