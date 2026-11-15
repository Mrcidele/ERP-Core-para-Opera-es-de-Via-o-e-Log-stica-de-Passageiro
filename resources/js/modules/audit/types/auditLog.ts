// Espelha App\Presentation\Http\Resources\Audit\AuditLogResource.

export type AuditValues = Record<string, unknown>;

export interface AuditLog {
    id: string;
    action: string;
    auditable_type: string;
    auditable_id: string | null;
    old_values: AuditValues | null;
    new_values: AuditValues | null;
    ip_address: string | null;
    /** ISO 8601 em UTC. */
    created_at: string;
    /** Quem fez; null quando ninguém estava autenticado (ex.: login falho) ou o usuário foi excluído. */
    user: { id: string; name: string } | null;
}

const ACTION_LABELS: Record<string, string> = {
    'vehicle.deleted': 'Veículo excluído',
    'trip.completed': 'Viagem concluída',
    'trip.canceled': 'Viagem cancelada',
    'maintenance_order.opened': 'Ordem de serviço aberta',
    'maintenance_order.completed': 'Ordem de serviço concluída',
    'auth.login_failed': 'Login recusado',
};

const ENTITY_LABELS: Record<string, string> = {
    vehicle: 'Veículo',
    trip: 'Viagem',
    maintenance_order: 'Ordem de serviço',
    user: 'Usuário',
};

/** Ação em português; uma ação nova que a tela ainda não conhece aparece com o código da API. */
export const auditActionLabel = (action: string): string => ACTION_LABELS[action] ?? action;

export const auditEntityLabel = (type: string): string => ENTITY_LABELS[type] ?? type;

/** Ações que merecem destaque na lista: exclusões e acessos recusados. */
export const isSensitiveAction = (action: string): boolean => action.endsWith('.deleted') || action === 'auth.login_failed';
