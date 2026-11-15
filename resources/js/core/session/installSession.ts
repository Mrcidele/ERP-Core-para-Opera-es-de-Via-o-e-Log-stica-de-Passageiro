import type { Router } from 'vue-router';
import { configureHttp } from '@/core/api/http';
import { resolveNavigation } from '@/core/router/guards';
import { useToast } from '@/core/ui/useToast';
import { useAuthStore } from '@/modules/auth/stores/authStore';

/**
 * Liga a sessão ao cliente HTTP, aos avisos e ao router. Precisa do Pinia já instalado.
 *
 * - toda requisição leva o token;
 * - 401 encerra a sessão local e leva ao login;
 * - 403, 409 e 422 viram avisos (acesso negado, conflito, validação);
 * - a navegação exige sessão e a permissão da rota.
 */
export function installSession(router: Router): void {
    const auth = useAuthStore();
    const toast = useToast();

    configureHttp({
        getToken: () => auth.token,
        onUnauthorized: () => {
            const hadSession = auth.isAuthenticated;
            const current = router.currentRoute.value;

            auth.clearSession();

            if (current.name !== 'login') {
                if (hadSession) {
                    toast.warning('Entre de novo para continuar.', 'Sua sessão terminou');
                }

                void router.push({ name: 'login', query: current.fullPath === '/' ? {} : { redirect: current.fullPath } });
            }
        },
        onForbidden: (message) => toast.error(message, 'Acesso negado'),
        onConflict: (message) => toast.error(message, 'Conflito'),
        onValidationFailed: (message) => toast.error(message, 'Validação'),
    });

    router.beforeEach((to) => resolveNavigation(to, auth, (message) => toast.error(message, 'Acesso negado')));
}
