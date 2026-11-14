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

/** Item da listagem (TripSummaryResource): a viagem com linha, veículo e motorista. */
export interface TripSummary {
    id: string;
    status: TripStatus;
    /** ISO 8601 em UTC. */
    departure_time: string;
    /** ISO 8601 em UTC. */
    arrival_time: string;
    line: { id: string; origin: string; destination: string; distance_km: number };
    vehicle: { id: string; plate: string };
    driver: { id: string; name: string };
}

export const TRIP_TRANSITIONS = ['start', 'complete', 'cancel'] as const;
export type TripTransition = (typeof TRIP_TRANSITIONS)[number];

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
