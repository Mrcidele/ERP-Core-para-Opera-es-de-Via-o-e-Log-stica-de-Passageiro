<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\CreateLineAction;
use App\Domain\Operations\Actions\DeleteLineAction;
use App\Domain\Operations\Actions\UpdateLineAction;
use App\Domain\Operations\DTOs\LineDataDTO;
use App\Domain\Operations\DTOs\LineStopDTO;
use App\Domain\Operations\Entities\LineStop;
use App\Domain\Operations\Exceptions\InvalidLineException;
use App\Domain\Operations\Exceptions\InvalidLineStopsException;
use App\Domain\Operations\Exceptions\LineHasActiveTripsException;
use App\Domain\Operations\Exceptions\LineRecordNotFoundException;
use App\Domain\Shared\Events\LineCreated;
use App\Domain\Shared\Events\LineDeleted;
use App\Domain\Shared\Events\LineUpdated;
use Tests\Fakes\ImmediateTransactionRunner;
use Tests\Fakes\InMemoryLineRepository;
use Tests\Fakes\RecordingEventDispatcher;

/**
 * @param  list<array{string, int, int}>  $stops
 */
function lineData(array $stops = [['Ponta Grossa', 115, 105], ['Castro', 160, 150]], string $destination = 'Ourinhos'): LineDataDTO
{
    return new LineDataDTO('Curitiba', $destination, 400, 390, array_map(
        fn (array $stop): LineStopDTO => new LineStopDTO(...$stop),
        $stops,
    ));
}

beforeEach(function (): void {
    $this->lines = new InMemoryLineRepository;
    $this->events = new RecordingEventDispatcher;
    $this->transaction = new ImmediateTransactionRunner;
    $this->create = new CreateLineAction($this->lines, $this->events);
});

it('creates a line numbering the stops in the order they were sent', function (): void {
    $line = $this->create->execute(lineData());

    expect($this->lines->findActive($line->id))->toBe($line)
        ->and($line->stops)->toEqual([new LineStop(1, 'Ponta Grossa', 115, 105), new LineStop(2, 'Castro', 160, 150)])
        ->and($this->events->events)->toEqual([new LineCreated($line->id, [
            'origin' => 'Curitiba',
            'destination' => 'Ourinhos',
            'distance_km' => 400,
            'estimated_duration_minutes' => 390,
            'stops' => 'Ponta Grossa (km 115, 105 min) → Castro (km 160, 150 min)',
        ])]);
});

it('creates a line without stops', function (): void {
    expect($this->create->execute(lineData([]))->stops)->toBe([]);
});

it('rejects stops out of order or beyond the destination', function (array $stops): void {
    expect(fn () => $this->create->execute(lineData($stops)))->toThrow(InvalidLineStopsException::class)
        ->and($this->lines->lines)->toBe([])
        ->and($this->events->events)->toBe([]);
})->with([
    'km igual ao da parada anterior' => [[['A', 100, 90], ['B', 100, 120]]],
    'km menor que o da parada anterior' => [[['A', 200, 90], ['B', 150, 120]]],
    'parada no km zero' => [[['A', 0, 0]]],
    'parada no km do destino' => [[['A', 400, 380]]],
    'tempo menor que o da parada anterior' => [[['A', 100, 120], ['B', 150, 90]]],
    'tempo igual à duração da linha' => [[['A', 100, 390]]],
]);

it('rejects a line whose destination is the origin', function (): void {
    expect(fn () => $this->create->execute(lineData([], 'curitiba')))->toThrow(InvalidLineException::class);
});

it('updates a line replacing its stops and records before and after', function (): void {
    $line = $this->create->execute(lineData());

    $updated = (new UpdateLineAction($this->lines, $this->events))->execute($line->id, lineData([['Jaguariaíva', 240, 230]], 'Jacarezinho'));

    expect($updated->id)->toBe($line->id)
        ->and($this->lines->findActive($line->id)?->destination)->toBe('Jacarezinho')
        ->and($updated->stops)->toEqual([new LineStop(1, 'Jaguariaíva', 240, 230)])
        ->and($this->events->events[1])->toEqual(new LineUpdated($line->id, $line->snapshot(), $updated->snapshot()));
});

it('deletes a line without active trips', function (): void {
    $line = $this->create->execute(lineData());

    (new DeleteLineAction($this->lines, $this->events, $this->transaction))->execute($line->id);

    expect($this->lines->findActive($line->id))->toBeNull()
        ->and($this->events->events[1])->toEqual(new LineDeleted($line->id, $line->snapshot()));
});

it('refuses to delete a line with scheduled or running trips', function (): void {
    $line = $this->create->execute(lineData());
    $this->lines->linesWithActiveTrips[] = $line->id;

    expect(fn () => (new DeleteLineAction($this->lines, $this->events, $this->transaction))->execute($line->id))->toThrow(LineHasActiveTripsException::class)
        ->and($this->lines->findActive($line->id))->not->toBeNull()
        ->and($this->events->events)->toHaveCount(1);
});

it('fails with not found when the line does not exist', function (): void {
    expect(fn () => (new UpdateLineAction($this->lines, $this->events))->execute('missing', lineData()))->toThrow(LineRecordNotFoundException::class)
        ->and(fn () => (new DeleteLineAction($this->lines, $this->events, $this->transaction))->execute('missing'))->toThrow(LineRecordNotFoundException::class);
});

it('locks the line inside a transaction before checking its trips and deleting it', function (): void {
    $line = $this->create->execute(lineData());

    (new DeleteLineAction($this->lines, $this->events, $this->transaction))->execute($line->id);

    expect($this->lines->locked)->toBe([$line->id])
        ->and($this->transaction->transactions)->toBe(1);
});
