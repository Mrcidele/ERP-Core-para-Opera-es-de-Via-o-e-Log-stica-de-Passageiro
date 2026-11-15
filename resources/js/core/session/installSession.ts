import type { Router } from 'vue-router';
import { configureHttp } from '@/core/api/http';
import { resolveNavigation } from '@/core/router/guards';
import { useNotificationStore } from '@/core/stores/notificationStore';
import { useAuthStore } from '@/modules/auth/stores/authStore';

/**
 * Liga a sessão ao cliente HTTP e ao router. Precisa do Pinia já instalado.
 *
 * - toda requisição leva o token;
 * - 401 encerra a sessão local e leva ao login;
 * - 403 vira um aviso de acesso negado;
 * - a navegação exige sessão e a permissão da rota.
 */
export function installSession(router: Router): void {
    const auth = useAuthStore();
    const notifications = useNotificationStore();

    configureHttp({
        getToken: () => auth.token,
        onUnauthorized: () => {
            const hadSession = auth.isAuthenticated;
            const current = router.currentRoute.value;

            auth.clearSession();

            if (current.name !== 'login') {
                if (hadSession) {
                    notifications.notify('Sua sessão terminou. Entre de novo para continuar.');
                }

                void router.push({ name: 'login', query: current.fullPath === '/' ? {} : { redirect: current.fullPath } });
            }
        },
        onForbidden: (message) => notifications.notify(message),
    });

    router.beforeEach((to) => resolveNavigation(to, auth, (message) => notifications.notify(message)));
}
