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
        // Quem já entrou não vê de novo o login nem a página do produto.
        return session.isAuthenticated ? { name: 'dashboard' } : true;
    }

    if (!session.isAuthenticated) {
        // Quem chega pelo endereço principal sem sessão conhece o produto; o resto vai para o login.
        if (to.fullPath === '/') {
            return { name: 'landing' };
        }

        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.permission !== undefined && !session.can(to.meta.permission)) {
        onDenied(ACCESS_DENIED_MESSAGE);

        return { name: 'dashboard' };
    }

    return true;
}
