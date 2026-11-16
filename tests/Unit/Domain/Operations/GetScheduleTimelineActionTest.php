<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\GetScheduleTimelineAction;
use App\Domain\Operations\Contracts\ScheduleTimelineReader;
use App\Domain\Operations\DTOs\ScheduleTimelineDTO;

it('asks for the 48 hours from midnight UTC of the date, with the 11 hours of driver rest', function (string $date): void {
    $reader = new class implements ScheduleTimelineReader
    {
        /** @var list<mixed> */
        public array $asked = [];

        public function read(DateTimeImmutable $from, DateTimeImmutable $to, int $restHours): ScheduleTimelineDTO
        {
            $this->asked = [$from->format(DATE_ATOM), $to->format(DATE_ATOM), $restHours];

            return new ScheduleTimelineDTO($from, $to, $restHours, [], []);
        }
    };

    $timeline = (new GetScheduleTimelineAction($reader))->execute(new DateTimeImmutable($date));

    expect($reader->asked)->toBe(['2026-11-20T00:00:00+00:00', '2026-11-22T00:00:00+00:00', 11])
        ->and($timeline->driverRestHours)->toBe(11);
})->with(['2026-11-20 00:00:00+00:00', '2026-11-20 15:45:00+00:00']);
