<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use DateTimeImmutable;

/** Grade de escala de uma janela de tempo: o que cada veículo e cada motorista tem marcado. */
final readonly class ScheduleTimelineDTO
{
    /**
     * @param  list<TimelineVehicleDTO>  $vehicles
     * @param  list<TimelineDriverDTO>  $drivers
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public int $driverRestHours,
        public array $vehicles,
        public array $drivers,
    ) {}
}
