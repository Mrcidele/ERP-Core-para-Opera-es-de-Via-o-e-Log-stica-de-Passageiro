import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { ROLE_PERMISSIONS } from '@/tests/auth';
import { useAuthStore } from '../../stores/authStore';
import LoginView from '../LoginView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const Blank = { template: '<div />' };

async function mountLogin(path = '/login'): Promise<{ wrapper: VueWrapper; router: Router }> {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/login', name: 'login', component: LoginView, meta: { title: 'Entrar', public: true } },
            { path: '/', name: 'dashboard', component: Blank, meta: { title: 'Painel' } },
            { path: '/operacoes', name: 'operations', component: Blank, meta: { title: 'Operações' } },
        ],
    });

    await router.push(path);
    await router.isReady();

    const wrapper = mount(LoginView, { global: { plugins: [router] } });

    // Os perfis de teste são um componente carregado sob demanda.
    await vi.dynamicImportSettled();
    await flushPromises();

    return { wrapper, router };
}

const value = (wrapper: VueWrapper, field: string): string => (wrapper.get(`[data-test="${field}"]`).element as HTMLInputElement).value;

const session = {
    token: '1|plain-token',
    user: { id: 'user-1', name: 'Diego Despachante', email: 'despachante@erp.test', role: 'dispatcher' },
    permissions: ROLE_PERMISSIONS.dispatcher,
};

describe('LoginView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.post.mockReset();
    });

    it.each([
        ['admin', 'admin@erp.test'],
        ['dispatcher', 'despachante@erp.test'],
        ['fleet_manager', 'frota@erp.test'],
        ['mechanic_supervisor', 'oficina@erp.test'],
    ])('fills the %s test profile without submitting', async (role, email) => {
        const { wrapper } = await mountLogin();

        await wrapper.get(`[data-test="fill-${role}"]`).trigger('click');

        expect(value(wrapper, 'email')).toBe(email);
        expect(value(wrapper, 'password')).toBe('Password@123');
        expect(http.post).not.toHaveBeenCalled();
    });

    it('validates before calling the API', async () => {
        const { wrapper } = await mountLogin();

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="email-error"]').text()).toBe('Informe o e-mail.');
        expect(wrapper.get('[data-test="password-error"]').text()).toBe('Informe a senha.');

        await wrapper.get('[data-test="email"]').setValue('nao-e-email');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.get('[data-test="email-error"]').text()).toBe('Informe um e-mail válido.');
    });

    it('signs in and opens the dashboard', async () => {
        http.post.mockResolvedValue({ data: { data: session } });
        const { wrapper, router } = await mountLogin();

        await wrapper.get('[data-test="fill-dispatcher"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/auth/login', { email: 'despachante@erp.test', password: 'Password@123' });
        expect(useAuthStore().isAuthenticated).toBe(true);
        expect(router.currentRoute.value.fullPath).toBe('/');
    });

    it('returns to the page the visitor was trying to open', async () => {
        http.post.mockResolvedValue({ data: { data: session } });
        const { wrapper, router } = await mountLogin('/login?redirect=/operacoes');

        await wrapper.get('[data-test="fill-dispatcher"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/operacoes');
    });

    it.each(['https://evil.test/', '//evil.test/', 'javascript:alert(1)'])('ignores an external redirect: %s', async (redirect) => {
        http.post.mockResolvedValue({ data: { data: session } });
        const { wrapper, router } = await mountLogin(`/login?redirect=${encodeURIComponent(redirect)}`);

        await wrapper.get('[data-test="fill-dispatcher"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/');
    });

    it('shows the refused credentials on the e-mail field and stays on the login', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { email: ['E-mail ou senha incorretos.'] } } },
        });
        const { wrapper, router } = await mountLogin();

        await wrapper.get('[data-test="email"]').setValue('despachante@erp.test');
        await wrapper.get('[data-test="password"]').setValue('errada');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="email-error"]').text()).toBe('E-mail ou senha incorretos.');
        expect(router.currentRoute.value.fullPath).toBe('/login');
        expect(value(wrapper, 'password')).toBe('errada');
    });

    it('shows the rate limit message', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 429, data: { message: 'Muitas tentativas. Aguarde um minuto e tente de novo.' } },
        });
        const { wrapper } = await mountLogin();

        await wrapper.get('[data-test="fill-admin"]').trigger('click');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="error"]').text()).toContain('Muitas tentativas');
    });
});
