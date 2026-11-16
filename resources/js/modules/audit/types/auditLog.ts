// Espelha App\Presentation\Http\Resources\Audit\AuditLogResource.

export type AuditValues = Record<string, unknown>;

export interface AuditLog {
    id: string;
    /** Código técnico da ação (ex.: `vehicle.deleted`). */
    action: string;
    /** Nome da ação em português, definido pela API. */
    action_label: string;
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

const ENTITY_LABELS: Record<string, string> = {
    vehicle: 'Veículo',
    line: 'Linha',
    trip: 'Viagem',
    maintenance_order: 'Ordem de serviço',
    fuel_log: 'Abastecimento',
    user: 'Usuário',
};

export const auditEntityLabel = (type: string): string => ENTITY_LABELS[type] ?? type;

/** Ações que merecem destaque na lista: exclusões e acessos recusados. */
export const isSensitiveAction = (action: string): boolean => action.endsWith('.deleted') || action === 'auth.login_failed';
