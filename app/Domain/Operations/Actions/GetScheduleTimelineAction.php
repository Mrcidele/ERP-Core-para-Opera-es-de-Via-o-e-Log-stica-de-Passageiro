<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Contracts\ScheduleTimelineReader;
use App\Domain\Operations\DTOs\ScheduleTimelineDTO;
use App\Domain\Operations\Entities\Trip;
use DateTimeImmutable;
use DateTimeZone;

final readonly class GetScheduleTimelineAction
{
    public const WINDOW_HOURS = 48;

    public function __construct(
        private ScheduleTimelineReader $reader,
    ) {}

    /** Grade das 48 horas que começam à meia-noite (UTC) do dia informado. */
    public function execute(DateTimeImmutable $date): ScheduleTimelineDTO
    {
        $from = new DateTimeImmutable($date->format('Y-m-d').' 00:00:00', new DateTimeZone('UTC'));

        return $this->reader->read($from, $from->modify('+'.self::WINDOW_HOURS.' hours'), Trip::DRIVER_REST_HOURS);
    }
}
