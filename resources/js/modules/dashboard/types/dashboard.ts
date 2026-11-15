// Espelha App\Presentation\Http\Resources\Dashboard\DashboardSummaryResource.
// Cada bloco vem null quando o usuário não tem a permissão de consulta do módulo.

export interface DashboardVehicleDue {
    id: string;
    plate: string;
    current_mileage: number;
    next_preventive_maintenance_km: number;
    needs_preventive_maintenance: boolean;
}

export interface DashboardFleet {
    total: number;
    active: number;
    maintenance: number;
    inactive: number;
    /** Percentual da frota ativa, com uma casa decimal. */
    availability_rate: number;
    preventive_maintenance_due: DashboardVehicleDue[];
}

export interface DashboardUpcomingTrip {
    id: string;
    /** ISO 8601 em UTC. */
    departure_time: string;
    /** ISO 8601 em UTC. */
    arrival_time: string;
    line: { origin: string; destination: string };
    vehicle: { plate: string };
    driver: { name: string };
}

export interface DashboardOperations {
    in_progress_now: number;
    scheduled_next_24h: number;
    next_trips: DashboardUpcomingTrip[];
}

export interface DashboardExpiringLicense {
    employee_id: string;
    name: string;
    /** Data no formato AAAA-MM-DD. */
    cnh_expiration_date: string;
    /** Zero ou negativo quando a CNH já venceu. */
    days_remaining: number;
}

export interface DashboardCrew {
    expiring_licenses: DashboardExpiringLicense[];
}

export interface DashboardMaintenance {
    completed_last_30_days: { count: number; total_cost_cents: number };
    open: number;
    in_progress: number;
}

export interface DashboardSummary {
    /** ISO 8601 em UTC. */
    generated_at: string;
    fleet: DashboardFleet | null;
    operations: DashboardOperations | null;
    crew: DashboardCrew | null;
    maintenance: DashboardMaintenance | null;
}
