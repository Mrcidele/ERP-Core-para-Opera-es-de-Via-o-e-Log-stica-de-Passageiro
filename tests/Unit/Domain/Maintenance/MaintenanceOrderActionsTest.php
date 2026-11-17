<?php

declare(strict_types=1);

use App\Domain\Maintenance\Actions\CancelMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\CompleteMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\ExportMaintenanceOrdersAction;
use App\Domain\Maintenance\Actions\ListMaintenanceOrdersAction;
use App\Domain\Maintenance\Actions\OpenMaintenanceOrderAction;
use App\Domain\Maintenance\DTOs\CompleteMaintenanceOrderDTO;
use App\Domain\Maintenance\DTOs\OpenMaintenanceOrderDTO;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use App\Domain\Maintenance\Exceptions\InvalidEstimatedCompletionException;
use App\Domain\Maintenance\Exceptions\InvalidMaintenanceTransitionException;
use App\Domain\Maintenance\Exceptions\MaintenanceOrderNotFoundException;
use App\Domain\Maintenance\Exceptions\MechanicNotAvailableException;
use App\Domain\Maintenance\Exceptions\NegativeMaintenanceCostException;
use App\Domain\Maintenance\Exceptions\VehicleNotFoundException;
use App\Domain\Operations\Actions\ScheduleTripAction;
use App\Domain\Operations\DTOs\ScheduleTripDTO;
use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Exceptions\VehicleNotAvailableException;
use App\Domain\Shared\Events\MaintenanceOrderCompleted;
use App\Domain\Shared\Events\MaintenanceOrderOpened;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\FakeCrewGateway;
use Tests\Fakes\FakeFleetGateway;
use Tests\Fakes\FixedClock;
use Tests\Fakes\ImmediateTransactionRunner;
use Tests\Fakes\InMemoryLineRepository;
use Tests\Fakes\InMemoryMaintenanceOrderRepository;
use Tests\Fakes\InMemoryTripRepository;
use Tests\Fakes\RecordingEventDispatcher;

function orderData(string $vehicle = 'bus-1', string $mechanic = 'mechanic-1'): OpenMaintenanceOrderDTO
{
    return new OpenMaintenanceOrderDTO($vehicle, $mechanic, MaintenanceType::Corrective, 'Reparo no ar-condicionado');
}

beforeEach(function (): void {
    $this->orders = new InMemoryMaintenanceOrderRepository;
    $this->fleet = new FakeFleetGateway(['bus-1', 'bus-2']);
    $this->crew = new FakeCrewGateway(activeDriverIds: ['driver-1'], activeMechanicIds: ['mechanic-1']);

    $this->events = new RecordingEventDispatcher;

    $this->open = new OpenMaintenanceOrderAction($this->orders, $this->fleet, $this->crew, new FixedClock('2026-11-13 10:00:00'), $this->events);
    $this->complete = new CompleteMaintenanceOrderAction($this->orders, $this->fleet, new FixedClock('2026-11-14 16:30:00'), $this->events);
    $this->cancel = new CancelMaintenanceOrderAction($this->orders, $this->fleet);
});

it('opens an order and puts the vehicle in maintenance', function (): void {
    $order = $this->open->execute(orderData());

    expect($order->status)->toBe(MaintenanceStatus::Open)
        ->and($order->type)->toBe(MaintenanceType::Corrective)
        ->and($order->description)->toBe('Reparo no ar-condicionado')
        ->and($order->costCents)->toBe(0)
        ->and($order->openedAt->format('Y-m-d H:i'))->toBe('2026-11-13 10:00')
        ->and($order->completedAt)->toBeNull()
        ->and($this->orders->findById($order->id))->toBe($order)
        ->and($this->fleet->statuses['bus-1'])->toBe('maintenance')
        ->and($this->fleet->statuses['bus-2'])->toBe('active')
        ->and($this->events->events)->toEqual([new MaintenanceOrderOpened($order->id, 'bus-1', 'mechanic-1', 'corrective')]);
});

it('rejects an unknown vehicle', function (): void {
    expect(fn () => $this->open->execute(orderData(vehicle: 'missing')))->toThrow(VehicleNotFoundException::class);

    expect($this->orders->orders)->toBeEmpty();
});

it('rejects an employee who is not an active mechanic and leaves the vehicle untouched', function (): void {
    expect(fn () => $this->open->execute(orderData(mechanic: 'driver-1')))->toThrow(MechanicNotAvailableException::class);

    expect($this->orders->orders)->toBeEmpty()
        ->and($this->fleet->statuses['bus-1'])->toBe('active');
});

it('completes an order with its final cost and returns the vehicle to service', function (): void {
    $order = $this->open->execute(orderData());

    $completed = $this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, 45_090));

    expect($completed->status)->toBe(MaintenanceStatus::Completed)
        ->and($completed->costCents)->toBe(45_090)
        ->and($completed->completedAt?->format('Y-m-d H:i'))->toBe('2026-11-14 16:30')
        ->and($this->orders->findById($order->id))->toBe($completed)
        ->and($this->fleet->statuses['bus-1'])->toBe('active')
        ->and($this->events->events[1])->toEqual(new MaintenanceOrderCompleted($order->id, 'bus-1', 45_090));
});

it('keeps the vehicle in maintenance while another order is still pending', function (): void {
    $first = $this->open->execute(orderData());
    $second = $this->open->execute(orderData());

    $this->complete->execute(new CompleteMaintenanceOrderDTO($first->id, 0));
    expect($this->fleet->statuses['bus-1'])->toBe('maintenance');

    $this->cancel->execute($second->id);
    expect($this->fleet->statuses['bus-1'])->toBe('active');
});

it('cancels an order and returns the vehicle to service', function (): void {
    $order = $this->open->execute(orderData());

    $canceled = $this->cancel->execute($order->id);

    expect($canceled->status)->toBe(MaintenanceStatus::Canceled)
        ->and($canceled->completedAt)->toBeNull()
        ->and($this->fleet->statuses['bus-1'])->toBe('active');
});

it('rejects a negative cost', function (): void {
    $order = $this->open->execute(orderData());

    expect(fn () => $this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, -1)))
        ->toThrow(NegativeMaintenanceCostException::class);

    expect($this->orders->findById($order->id)?->status)->toBe(MaintenanceStatus::Open)
        ->and($this->fleet->statuses['bus-1'])->toBe('maintenance');
});

it('rejects completing or canceling an order that is already closed', function (string $firstClose): void {
    $order = $this->open->execute(orderData());

    $firstClose === 'complete'
        ? $this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, 100))
        : $this->cancel->execute($order->id);

    expect(fn () => $this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, 200)))
        ->toThrow(InvalidMaintenanceTransitionException::class)
        ->and(fn () => $this->cancel->execute($order->id))
        ->toThrow(InvalidMaintenanceTransitionException::class);
})->with(['complete', 'cancel']);

it('fails for an unknown order', function (): void {
    expect(fn () => $this->complete->execute(new CompleteMaintenanceOrderDTO('missing', 0)))
        ->toThrow(MaintenanceOrderNotFoundException::class)
        ->and(fn () => $this->cancel->execute('missing'))
        ->toThrow(MaintenanceOrderNotFoundException::class);
});

it('lists orders from the most recent to the oldest', function (): void {
    $old = (new OpenMaintenanceOrderAction($this->orders, $this->fleet, $this->crew, new FixedClock('2026-11-01 08:00:00'), $this->events))->execute(orderData());
    $recent = $this->open->execute(orderData(vehicle: 'bus-2'));

    $result = (new ListMaintenanceOrdersAction($this->orders))->execute();

    expect(array_map(fn ($summary): string => $summary->order->id, $result->items))->toBe([$recent->id, $old->id]);
});

it('pages the orders and exports every one of them', function (): void {
    $old = (new OpenMaintenanceOrderAction($this->orders, $this->fleet, $this->crew, new FixedClock('2026-11-01 08:00:00'), $this->events))->execute(orderData());
    $recent = $this->open->execute(orderData(vehicle: 'bus-2'));

    $page = (new ListMaintenanceOrdersAction($this->orders))->execute(new PageQuery(page: 2, perPage: 1));
    $exported = (new ExportMaintenanceOrdersAction($this->orders))->execute(new PageQuery(page: 2, perPage: 1));

    expect(array_map(fn ($summary): string => $summary->order->id, $page->items))->toBe([$old->id])
        ->and($page->lastPage())->toBe(2)
        ->and(array_map(fn ($summary): string => $summary->order->id, [...$exported]))->toBe([$recent->id, $old->id]);
});

it('blocks scheduling trips for a vehicle with a pending order and frees it when the order is completed', function (): void {
    $lines = new InMemoryLineRepository;
    $lines->add(new Line('line-1', 'Curitiba', 'Ourinhos', 400, 390));
    $trips = new InMemoryTripRepository;
    $schedule = new ScheduleTripAction($trips, $lines, $this->fleet, $this->crew, new FixedClock('2026-11-13 10:00:00'), new ImmediateTransactionRunner, new RecordingEventDispatcher);
    $utc = new DateTimeZone('UTC');
    $trip = new ScheduleTripDTO('line-1', 'bus-1', 'driver-1', new DateTimeImmutable('2026-11-20 08:00', $utc), new DateTimeImmutable('2026-11-20 14:30', $utc));

    $order = $this->open->execute(orderData());

    expect(fn () => $schedule->execute($trip))->toThrow(VehicleNotAvailableException::class);

    $this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, 10_000));
    $schedule->execute($trip);

    expect($trips->trips)->toHaveCount(1);
});

it('opens an order with an estimated completion and keeps it through completion', function (): void {
    $estimate = new DateTimeImmutable('2026-11-14 18:00:00', new DateTimeZone('UTC'));

    $order = $this->open->execute(new OpenMaintenanceOrderDTO('bus-1', 'mechanic-1', MaintenanceType::Preventive, 'Revisão dos freios', $estimate));

    expect($order->estimatedCompletionAt)->toEqual($estimate)
        ->and($this->complete->execute(new CompleteMaintenanceOrderDTO($order->id, 45_000))->estimatedCompletionAt)->toEqual($estimate);
});

it('rejects an estimated completion that is not after the opening', function (string $estimate): void {
    $data = new OpenMaintenanceOrderDTO('bus-1', 'mechanic-1', MaintenanceType::Corrective, 'Revisão dos freios', new DateTimeImmutable($estimate, new DateTimeZone('UTC')));

    expect(fn () => $this->open->execute($data))->toThrow(InvalidEstimatedCompletionException::class)
        ->and($this->orders->orders)->toBe([])
        ->and($this->fleet->statuses['bus-1'])->toBe('active');
})->with(['2026-11-13 10:00:00', '2026-11-12 08:00:00']);
