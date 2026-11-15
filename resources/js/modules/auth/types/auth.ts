// Espelha App\Domain\Identity\Enums (UserRole, Permission) e AuthenticatedUserResource / AuthSessionResource.

export const USER_ROLES = ['admin', 'dispatcher', 'fleet_manager', 'mechanic_supervisor'] as const;
export type UserRole = (typeof USER_ROLES)[number];

export const PERMISSIONS = [
    'fleet.view',
    'fleet.manage',
    'crew.view',
    'crew.manage',
    'operations.view',
    'operations.manage',
    'maintenance.view',
    'maintenance.manage',
    'audit.view',
] as const;
export type Permission = (typeof PERMISSIONS)[number];

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    role: UserRole;
}

/** Resposta de GET /auth/me. As permissões vêm sempre da API; o frontend não repete a matriz de acesso. */
export interface AuthenticatedUser {
    user: AuthUser;
    permissions: Permission[];
}

/** Resposta de POST /auth/login. */
export interface AuthSession extends AuthenticatedUser {
    token: string;
}

export const USER_ROLE_LABELS: Record<UserRole, string> = {
    admin: 'Administração',
    dispatcher: 'Despacho',
    fleet_manager: 'Gestão de frota',
    mechanic_supervisor: 'Supervisão de oficina',
};
