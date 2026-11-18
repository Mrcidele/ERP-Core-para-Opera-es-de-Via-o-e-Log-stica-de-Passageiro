import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type RouteRecordRaw, type Router } from 'vue-router';
import { http } from '@/core/api/http';
import { installSession } from '@/core/session/installSession';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { signInAs } from '@/tests/auth';
import { currentToasts } from '@/tests/toast';
import { ACCESS_DENIED_MESSAGE } from '../guards';
import { routes } from '../index';

const Blank = { template: '<div />' };

/** Mesmo mapa de rotas da aplicação (caminhos, nomes e permissões), com telas vazias. */
function makeRouter(): Router {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: routes.map(
            (route): RouteRecordRaw =>
                'component' in route
                    ? {
                          path: route.path,
                          component: Blank,
                          ...(route.name !== undefined ? { name: route.name } : {}),
                          ...(route.meta !== undefined ? { meta: route.meta } : {}),
                      }
                    : route,
        ),
    });

    installSession(router);

    return router;
}

async function visit(router: Router, path: string): Promise<string> {
    await router.push(path);
    await flushPromises();

    return router.currentRoute.value.fullPath;
}

describe('navigation guard', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it.each(['/frota', '/operacoes', '/equipe', '/manutencao'])('sends a visitor from %s to the login', async (path) => {
        const router = makeRouter();

        await visit(router, path);

        expect(router.currentRoute.value.name).toBe('login');
    });

    it('remembers where the visitor was going', async () => {
        const router = makeRouter();

        expect(await visit(router, '/operacoes')).toBe('/login?redirect=/operacoes');
    });

    it.each(['/', '/qualquer-coisa'])('shows the product page to a visitor who opens %s', async (path) => {
        expect(await visit(makeRouter(), path)).toBe('/apresentacao');
    });

    it('lets a visitor open the product page, and sends someone signed in to the dashboard', async () => {
        expect(await visit(makeRouter(), '/apresentacao')).toBe('/apresentacao');

        signInAs('dispatcher');

        expect(await visit(makeRouter(), '/apresentacao')).toBe('/');
    });

    it('lets a visitor open the login', async () => {
        expect(await visit(makeRouter(), '/login')).toBe('/login');
    });

    it('sends someone already signed in away from the login', async () => {
        signInAs('dispatcher');

        expect(await visit(makeRouter(), '/login')).toBe('/');
    });

    it.each([
        ['admin', ['/', '/frota', '/operacoes', '/equipe', '/manutencao'], []],
        ['dispatcher', ['/', '/frota', '/operacoes', '/equipe'], ['/manutencao']],
        ['fleet_manager', ['/', '/frota', '/operacoes', '/equipe', '/manutencao'], []],
        ['mechanic_supervisor', ['/', '/frota', '/manutencao'], ['/operacoes', '/equipe']],
    ] as const)('opens for %s only the areas its permissions allow', async (role, allowed, denied) => {
        signInAs(role);
        const router = makeRouter();

        for (const path of allowed) {
            expect(await visit(router, path)).toBe(path);
        }

        for (const path of denied) {
            expect(await visit(router, path)).toBe('/');
        }
    });

    it('warns when it blocks an area', async () => {
        signInAs('mechanic_supervisor');
        const router = makeRouter();

        await visit(router, '/operacoes');

        expect(currentToasts().map((toast) => [toast.title, toast.message])).toEqual([['Acesso negado', ACCESS_DENIED_MESSAGE]]);
    });

    it('applies new permissions without reloading', async () => {
        signInAs('mechanic_supervisor');
        const router = makeRouter();
        expect(await visit(router, '/operacoes')).toBe('/');

        signInAs('dispatcher');

        expect(await visit(router, '/operacoes')).toBe('/operacoes');
    });
});

describe('session and http', () => {
    beforeEach(() => setActivePinia(createPinia()));

    function failNextRequestWith(status: number, data: unknown): void {
        vi.spyOn(http.defaults, 'adapter', 'get').mockReturnValue((config) =>
            Promise.reject(Object.assign(new Error('fail'), { isAxiosError: true, config, response: { status, data, headers: {}, config, statusText: '' } })),
        );
    }

    it('on 401 clears the session, goes to the login and remembers the page', async () => {
        signInAs('admin');
        const router = makeRouter();
        await visit(router, '/frota');
        failNextRequestWith(401, { message: 'Não autenticado.' });

        await expect(http.get('/fleet/vehicles')).rejects.toBeDefined();
        await flushPromises();

        expect(useAuthStore().isAuthenticated).toBe(false);
        expect(router.currentRoute.value.fullPath).toBe('/login?redirect=/frota');
        expect(currentToasts()[0]).toMatchObject({ tone: 'warning', title: 'Sua sessão terminou' });

        vi.restoreAllMocks();
    });

    it('on 403 shows the access denied notice and keeps the session and the page', async () => {
        signInAs('dispatcher');
        const router = makeRouter();
        await visit(router, '/frota');
        failNextRequestWith(403, { message: 'Você não tem permissão para executar esta ação.' });

        await expect(http.delete('/fleet/vehicles/1')).rejects.toBeDefined();
        await flushPromises();

        expect(useAuthStore().isAuthenticated).toBe(true);
        expect(router.currentRoute.value.fullPath).toBe('/frota');
        expect(currentToasts().map((toast) => [toast.tone, toast.title, toast.message])).toEqual([
            ['error', 'Acesso negado', 'Você não tem permissão para executar esta ação.'],
        ]);

        vi.restoreAllMocks();
    });

    it('sends the token of the signed-in user', async () => {
        signInAs('admin');
        makeRouter();
        let authorization: unknown;
        vi.spyOn(http.defaults, 'adapter', 'get').mockReturnValue((config) => {
            authorization = config.headers.get('Authorization');

            return Promise.resolve({ data: {}, status: 200, statusText: '', headers: {}, config });
        });

        await http.get('/auth/me');

        expect(authorization).toBe('Bearer test-token');

        vi.restoreAllMocks();
    });

    it.each([
        [409, { message: 'A viagem já foi iniciada por outra pessoa.' }, 'Conflito', 'A viagem já foi iniciada por outra pessoa.'],
        [422, { errors: { driver_id: ['O motorista já tem outra viagem nesse período.'] } }, 'Validação', 'O motorista já tem outra viagem nesse período.'],
        [422, { errors: { plate: ['x'], chassis: ['y'] } }, 'Validação', 'Revise os campos destacados e tente de novo.'],
    ])('on %i shows a clear alert and keeps the session', async (status, data, title, message) => {
        signInAs('admin');
        makeRouter();
        failNextRequestWith(status, data);

        await expect(http.post('/operations/trips', {})).rejects.toBeDefined();

        expect(useAuthStore().isAuthenticated).toBe(true);
        expect(currentToasts().map((toast) => [toast.tone, toast.title, toast.message])).toEqual([['error', title, message]]);

        vi.restoreAllMocks();
    });
});
