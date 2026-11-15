import { useAuthStore } from '@/modules/auth/stores/authStore';
import { PERMISSIONS, type Permission, type UserRole } from '@/modules/auth/types/auth';

/** Cópia, só para os testes, da matriz de acesso que a API aplica (UserRole::permissions). */
export const ROLE_PERMISSIONS: Record<UserRole, Permission[]> = {
    admin: [...PERMISSIONS],
    dispatcher: ['fleet.view', 'crew.view', 'operations.view', 'operations.manage'],
    fleet_manager: ['fleet.view', 'fleet.manage', 'crew.view', 'operations.view', 'maintenance.view', 'maintenance.manage'],
    mechanic_supervisor: ['fleet.view', 'maintenance.view', 'maintenance.manage'],
};

/** Abre uma sessão na store ativa do Pinia com as permissões do papel. */
export function signInAs(role: UserRole = 'admin'): void {
    useAuthStore().setSession({
        token: 'test-token',
        user: { id: 'user-1', name: 'Usuária de Teste', email: `${role}@erp.test`, role },
        permissions: ROLE_PERMISSIONS[role],
    });
}
