// Espelha App\Domain\Maintenance\Enums e os Resources MaintenanceOrderResource e MaintenanceOrderSummaryResource.

export const MAINTENANCE_TYPES = ['preventive', 'corrective'] as const;
export type MaintenanceType = (typeof MAINTENANCE_TYPES)[number];

export const MAINTENANCE_STATUSES = ['open', 'in_progress', 'completed', 'canceled'] as const;
export type MaintenanceStatus = (typeof MAINTENANCE_STATUSES)[number];

interface MaintenanceOrderBase {
    id: string;
    type: MaintenanceType;
    status: MaintenanceStatus;
    description: string;
    cost_cents: number;
    /** ISO 8601 em UTC. */
    opened_at: string;
    /** ISO 8601 em UTC. */
    completed_at: string | null;
    /** Previsão de o veículo sair da oficina; ISO 8601 em UTC, null quando não informada. */
    estimated_completion_at: string | null;
}

/** Resposta da abertura e do encerramento (MaintenanceOrderResource). */
export interface MaintenanceOrder extends MaintenanceOrderBase {
    vehicle_id: string;
    mechanic_id: string;
}

/** Item da listagem (MaintenanceOrderSummaryResource). */
export interface MaintenanceOrderSummary extends MaintenanceOrderBase {
    vehicle: { id: string; plate: string };
    mechanic: { id: string; name: string };
}

export const MAINTENANCE_TYPE_LABELS: Record<MaintenanceType, string> = {
    preventive: 'Preventiva',
    corrective: 'Corretiva',
};

export const MAINTENANCE_STATUS_LABELS: Record<MaintenanceStatus, string> = {
    open: 'Aberta',
    in_progress: 'Em andamento',
    completed: 'Concluída',
    canceled: 'Cancelada',
};

/** Opção do formulário de abertura (MechanicResource): mecânicos ativos. */
export interface Mechanic {
    id: string;
    name: string;
}

/** A ordem ainda mantém o veículo parado? */
export const isPending = (status: MaintenanceStatus): boolean => status === 'open' || status === 'in_progress';
