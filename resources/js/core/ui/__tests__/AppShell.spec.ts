import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { signInAs } from '@/tests/auth';
import AppShell from '../AppShell.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const Blank = { template: '<div />' };

async function mountShell(): Promise<{ wrapper: VueWrapper; router: Router }> {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: ['dashboard', 'fleet', 'fuel', 'operations', 'lines', 'crew', 'maintenance', 'users', 'audit', 'login'].map((name) => ({
            path: name === 'dashboard' ? '/' : `/${name}`,
            name,
            component: Blank,
            meta: { title: name },
        })),
    });

    await router.push('/');
    await router.isReady();

    return { wrapper: mount(AppShell, { global: { plugins: [router] } }), router };
}

const menu = (wrapper: VueWrapper): string[] => wrapper.findAll('nav a').map((link) => link.text());

describe('AppShell', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.post.mockReset();
    });

    it.each([
        ['admin', ['Painel', 'Frota', 'Abastecimento', 'Operações', 'Rotas', 'Equipe', 'Manutenção', 'Usuários', 'Auditoria']],
        ['dispatcher', ['Painel', 'Frota', 'Abastecimento', 'Operações', 'Rotas', 'Equipe']],
        ['fleet_manager', ['Painel', 'Frota', 'Abastecimento', 'Operações', 'Rotas', 'Equipe', 'Manutenção']],
        ['mechanic_supervisor', ['Painel', 'Frota', 'Abastecimento', 'Manutenção']],
    ] as const)('shows to %s only the areas it can open', async (role, expected) => {
        signInAs(role);

        expect(menu((await mountShell()).wrapper)).toEqual(expected);
    });

    it('shows who is signed in and with which role', async () => {
        signInAs('mechanic_supervisor');
        const { wrapper } = await mountShell();

        expect(wrapper.get('[data-test="user-name"]').text()).toBe('Usuária de Teste');
        expect(wrapper.get('[data-test="user-role"]').text()).toBe('Supervisão de oficina');
    });

    it('signs out, revoking the token, and goes to the login', async () => {
        http.post.mockResolvedValue({});
        signInAs('admin');
        const { wrapper, router } = await mountShell();

        await wrapper.get('[data-test="sign-out"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/auth/logout');
        expect(useAuthStore().isAuthenticated).toBe(false);
        expect(router.currentRoute.value.name).toBe('login');
    });

    it('shows the initials of the signed-in user and the current section in the header', async () => {
        signInAs('admin');
        const { wrapper, router } = await mountShell();

        expect(wrapper.get('[data-test="user-initials"]').text()).toBe('UT');

        await router.push('/fleet');
        await flushPromises();

        expect(wrapper.get('[data-test="header-title"]').text()).toBe('fleet');
    });

    it('opens the navigation on small screens and closes it after navigating', async () => {
        signInAs('admin');
        const { wrapper, router } = await mountShell();
        const sidebar = wrapper.get('[data-test="sidebar"]');

        expect(sidebar.classes()).toContain('-translate-x-full');

        await wrapper.get('[data-test="menu-toggle"]').trigger('click');
        expect(sidebar.classes()).toContain('translate-x-0');
        expect(wrapper.get('[data-test="menu-toggle"]').attributes('aria-expanded')).toBe('true');

        await router.push('/fleet');
        await flushPromises();

        expect(sidebar.classes()).toContain('-translate-x-full');
    });
});
