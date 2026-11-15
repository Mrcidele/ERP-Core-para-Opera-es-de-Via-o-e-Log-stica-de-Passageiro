import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';

/** O que a guarda precisa saber da sessão. */
export interface NavigationSession {
    isAuthenticated: boolean;
    can: (permission: string) => boolean;
}

export const ACCESS_DENIED_MESSAGE = 'Você não tem acesso a essa área.';

/**
 * Decide o destino de uma navegação: `true` segue, ou a rota para onde redirecionar.
 * Rotas internas exigem sessão; rotas com `meta.permission` exigem a permissão.
 */
export function resolveNavigation(
    to: RouteLocationNormalized,
    session: NavigationSession,
    onDenied: (message: string) => void = () => undefined,
): true | RouteLocationRaw {
    if (to.meta.public) {
        // Quem já entrou não vê a tela de login de novo.
        return session.isAuthenticated ? { name: 'dashboard' } : true;
    }

    if (!session.isAuthenticated) {
        return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } };
    }

    if (to.meta.permission !== undefined && !session.can(to.meta.permission)) {
        onDenied(ACCESS_DENIED_MESSAGE);

        return { name: 'dashboard' };
    }

    return true;
}
