// Espelha App\Domain\Operations\Enums\TripStatus e os Resources TripResource e LineResource.

export const TRIP_STATUSES = ['scheduled', 'in_progress', 'completed', 'canceled'] as const;
export type TripStatus = (typeof TRIP_STATUSES)[number];

export interface Trip {
    id: string;
    line_id: string;
    vehicle_id: string;
    driver_id: string;
    /** ISO 8601 em UTC. */
    departure_time: string;
    /** ISO 8601 em UTC. */
    arrival_time: string;
    status: TripStatus;
}

export interface Line {
    id: string;
    origin: string;
    destination: string;
    distance_km: number;
    estimated_duration_minutes: number;
}

export const TRIP_STATUS_LABELS: Record<TripStatus, string> = {
    scheduled: 'Agendada',
    in_progress: 'Em andamento',
    completed: 'Concluída',
    canceled: 'Cancelada',
};
