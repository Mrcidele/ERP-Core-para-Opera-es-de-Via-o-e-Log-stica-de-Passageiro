<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class TimelineVehicleDTO
{
    /**
     * @param  list<TimelineTripDTO>  $trips
     * @param  list<TimelineMaintenanceDTO>  $maintenanceOrders
     */
    public function __construct(
        public string $id,
        public string $plate,
        public string $status,
        public array $trips,
        public array $maintenanceOrders,
    ) {}
}
