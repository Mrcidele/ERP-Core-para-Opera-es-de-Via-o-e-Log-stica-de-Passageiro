// Espelha App\Presentation\Http\Resources\Operations\ScheduleTimelineResource.
import type { VehicleStatus } from '@/modules/fleet/types/vehicle';
import type { MaintenanceStatus, MaintenanceType } from '@/modules/maintenance/types/maintenance';
import type { TripStatus } from './trip';

export interface TimelineTrip {
    id: string;
    /** Viagens canceladas não entram na grade. */
    status: Exclude<TripStatus, 'canceled'>;
    /** ISO 8601 em UTC. */
    departure_time: string;
    /** ISO 8601 em UTC. */
    arrival_time: string;
    /** Fim do descanso obrigatório do motorista depois da viagem. ISO 8601 em UTC. */
    rest_until: string;
    line: { origin: string; destination: string };
    vehicle: { id: string; plate: string };
    driver: { id: string; name: string };
}

/** Ordem de serviço pendente: sem previsão de término, ocupa o veículo de `opened_at` em diante. */
export interface TimelineMaintenanceOrder {
    id: string;
    type: MaintenanceType;
    status: MaintenanceStatus;
    /** ISO 8601 em UTC. */
    opened_at: string;
}

export interface TimelineVehicle {
    id: string;
    plate: string;
    /** Ativo, ou em manutenção quando só aparece por ter ordem de serviço pendente. */
    status: VehicleStatus;
    trips: TimelineTrip[];
    maintenance_orders: TimelineMaintenanceOrder[];
}

export interface TimelineDriver {
    id: string;
    name: string;
    trips: TimelineTrip[];
}

export interface ScheduleTimeline {
    /** Início da janela de 48 horas: meia-noite UTC da data pedida. ISO 8601. */
    from: string;
    to: string;
    driver_rest_hours: number;
    vehicles: TimelineVehicle[];
    drivers: TimelineDriver[];
}

/** O que a grade pede ao formulário de viagem quando o controlador escolhe um horário livre. */
export interface TripPrefill {
    vehicle_id?: string;
    driver_id?: string;
    /** Partida no formato de <input type="datetime-local">, no fuso do navegador. */
    departure_time?: string;
}
