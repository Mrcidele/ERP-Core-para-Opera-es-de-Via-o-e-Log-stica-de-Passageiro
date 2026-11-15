import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROLE_PERMISSIONS, signInAs } from '@/tests/auth';
import { PERMISSIONS, USER_ROLES } from '../../types/auth';
import { AUTH_STORAGE_KEY, useAuthStore } from '../authStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const session = {
    token: '1|plain-token',
    user: { id: 'user-1', name: 'Diego Despachante', email: 'despachante@erp.test', role: 'dispatcher' },
    permissions: ROLE_PERMISSIONS.dispatcher,
};

describe('authStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
        http.post.mockReset();
    });

    describe('can()', () => {
        it('is false for everything without a session', () => {
            const store = useAuthStore();

            expect(store.isAuthenticated).toBe(false);
            expect(PERMISSIONS.filter((permission) => store.can(permission))).toEqual([]);
        });

        it.each(USER_ROLES)('allows exactly the permissions of the %s role', (role) => {
            signInAs(role);
            const store = useAuthStore();

            expect(PERMISSIONS.filter((permission) => store.can(permission))).toEqual(
                PERMISSIONS.filter((permission) => ROLE_PERMISSIONS[role].includes(permission)),
            );
        });

        it('follows the access matrix in the cases that matter most', () => {
            signInAs('mechanic_supervisor');
            const mechanic = useAuthStore();

            expect(mechanic.can('maintenance.manage')).toBe(true);
            expect(mechanic.can('operations.manage')).toBe(false);
            expect(mechanic.can('fleet.manage')).toBe(false);
            expect(mechanic.can('crew.view')).toBe(false);

            signInAs('dispatcher');

            expect(mechanic.can('operations.manage')).toBe(true);
            expect(mechanic.can('maintenance.view')).toBe(false);
            expect(mechanic.can('audit.view')).toBe(false);
        });

        it('is false for a permission that does not exist', () => {
            signInAs('admin');

            expect(useAuthStore().can('finance.manage')).toBe(false);
        });
    });

    it('logs in, keeps the session and persists it', async () => {
        http.post.mockResolvedValue({ data: { data: session } });
        const store = useAuthStore();

        const ok = await store.login({ email: 'despachante@erp.test', password: 'Password@123' });

        expect(ok).toBe(true);
        expect(http.post).toHaveBeenCalledWith('/auth/login', { email: 'despachante@erp.test', password: 'Password@123' });
        expect(store.isAuthenticated).toBe(true);
        expect(store.user?.name).toBe('Diego Despachante');
        expect(store.can('operations.manage')).toBe(true);
        expect(JSON.parse(localStorage.getItem(AUTH_STORAGE_KEY) ?? '{}')).toEqual(session);
    });

    it('never persists the password', async () => {
        http.post.mockResolvedValue({ data: { data: session } });

        await useAuthStore().login({ email: 'despachante@erp.test', password: 'Password@123' });

        expect(localStorage.getItem(AUTH_STORAGE_KEY)).not.toContain('Password@123');
    });

    it('restores the persisted session in a new store', () => {
        localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));

        const store = useAuthStore();

        expect(store.isAuthenticated).toBe(true);
        expect(store.token).toBe('1|plain-token');
        expect(store.can('operations.manage')).toBe(true);
    });

    it.each(['not json', '{"token":123}', '{"token":"x"}', 'null'])('ignores corrupted storage: %s', (raw) => {
        localStorage.setItem(AUTH_STORAGE_KEY, raw);

        expect(useAuthStore().isAuthenticated).toBe(false);
    });

    it('keeps the field error and no session when the credentials are refused', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { email: ['E-mail ou senha incorretos.'] } } },
        });
        const store = useAuthStore();

        const ok = await store.login({ email: 'despachante@erp.test', password: 'errada' });

        expect(ok).toBe(false);
        expect(store.isAuthenticated).toBe(false);
        expect(store.validationErrors.email?.[0]).toBe('E-mail ou senha incorretos.');
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
    });

    it('shows the rate limit message from the API', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 429, data: { message: 'Muitas tentativas. Aguarde um minuto e tente de novo.' } },
        });
        const store = useAuthStore();

        await store.login({ email: 'despachante@erp.test', password: 'errada' });

        expect(store.loginError).toBe('Muitas tentativas. Aguarde um minuto e tente de novo.');
    });

    it('logs out by revoking the token and clearing the session', async () => {
        http.post.mockResolvedValue({});
        signInAs('admin');
        const store = useAuthStore();

        await store.logout();

        expect(http.post).toHaveBeenCalledWith('/auth/logout');
        expect(store.isAuthenticated).toBe(false);
        expect(store.can('fleet.view')).toBe(false);
        expect(localStorage.getItem(AUTH_STORAGE_KEY)).toBeNull();
    });

    it('clears the session even when the logout request fails', async () => {
        http.post.mockRejectedValue(new Error('network'));
        signInAs('admin');
        const store = useAuthStore();

        await store.logout();

        expect(store.isAuthenticated).toBe(false);
    });

    it('refreshes the user and permissions from the API', async () => {
        signInAs('admin');
        http.get.mockResolvedValue({ data: { data: { user: { ...session.user, role: 'mechanic_supervisor' }, permissions: ROLE_PERMISSIONS.mechanic_supervisor } } });
        const store = useAuthStore();

        await store.refresh();

        expect(http.get).toHaveBeenCalledWith('/auth/me');
        expect(store.token).toBe('test-token');
        expect(store.user?.role).toBe('mechanic_supervisor');
        expect(store.can('audit.view')).toBe(false);
    });

    it('does not call the API to refresh without a session, and keeps the session if the refresh fails', async () => {
        const store = useAuthStore();
        await store.refresh();
        expect(http.get).not.toHaveBeenCalled();

        signInAs('admin');
        http.get.mockRejectedValue(new Error('network'));
        await store.refresh();

        expect(store.isAuthenticated).toBe(true);
    });
});
