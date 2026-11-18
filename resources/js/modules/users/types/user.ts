// Espelha App\Presentation\Http\Resources\Identity\UserResource.
import type { UserRole } from '@/modules/auth/types/auth';

export const USER_STATUSES = ['active', 'inactive'] as const;
export type UserStatus = (typeof USER_STATUSES)[number];

export interface ManagedUser {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    active: boolean;
    /** ISO 8601 em UTC; null enquanto o usuário pode entrar. */
    deactivated_at: string | null;
}

export const USER_STATUS_LABELS: Record<UserStatus, string> = {
    active: 'Ativo',
    inactive: 'Desativado',
};

export const userStatus = (user: Pick<ManagedUser, 'active'>): UserStatus => (user.active ? 'active' : 'inactive');
