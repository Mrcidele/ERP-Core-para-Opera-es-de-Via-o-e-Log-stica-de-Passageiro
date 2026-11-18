import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { AxiosError, AxiosHeaders } from 'axios';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { signInAs } from '@/tests/auth';
import { page, pageParams } from '@/tests/paging';
import { lastToast } from '@/tests/toast';
import type { ManagedUser } from '../../types/user';
import UsersView from '../UsersView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const make = (id: string, name: string, active = true, role: ManagedUser['role'] = 'dispatcher'): ManagedUser => ({
    id,
    name,
    email: `${name.toLowerCase()}@erp.test`,
    role,
    role_label: { admin: 'Administração', dispatcher: 'Despacho', fleet_manager: 'Gestão de frota', mechanic_supervisor: 'Supervisão de oficina' }[role],
    active,
    deactivated_at: active ? null : '2026-11-01T12:00:00+00:00',
});

// "user-1" é a sessão aberta por signInAs.
const people = [make('user-1', 'Eu', true, 'admin'), make('2', 'Bruna'), make('3', 'Ana', false, 'mechanic_supervisor')];

const STRONG = 'Senha-Segura-2026';

function validationError(errors: Record<string, string[]>): AxiosError {
    return new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        statusText: 'Unprocessable Content',
        headers: {},
        config: { headers: new AxiosHeaders() },
        data: { message: 'invalid', errors },
    });
}

async function mountPage(users: ManagedUser[] = people): Promise<VueWrapper> {
    http.get.mockResolvedValue(page(users));
    const wrapper = mount(UsersView);
    await flushPromises();

    return wrapper;
}

const names = (wrapper: VueWrapper): string[] => wrapper.findAll('[data-test="user-row"] td:first-child').map((cell) => cell.text().replace('(você)', '').trim());

describe('UsersView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('lists the users with profile and status from the first page', async () => {
        const wrapper = await mountPage();

        expect(http.get).toHaveBeenCalledWith('/identity/users', pageParams());
        expect(names(wrapper)).toEqual(['Eu', 'Bruna', 'Ana']);
        expect(wrapper.findAll('[data-test="status-badge"]').map((badge) => badge.text())).toEqual(['Ativo', 'Ativo', 'Desativado']);
        expect(wrapper.text()).toContain('Supervisão de oficina');
    });

    it('marks the signed-in user and offers no button to deactivate them', async () => {
        const wrapper = await mountPage();
        const rows = wrapper.findAll('[data-test="user-row"]');

        expect(rows[0]!.find('[data-test="you"]').exists()).toBe(true);
        expect(rows[0]!.find('[data-test="toggle"]').exists()).toBe(false);
        expect(rows[1]!.get('[data-test="toggle"]').attributes('aria-label')).toBe('Desativar Bruna');
        expect(rows[2]!.get('[data-test="toggle"]').attributes('aria-label')).toBe('Reativar Ana');
    });

    it('filters inactive users on the server', async () => {
        const wrapper = await mountPage();

        http.get.mockResolvedValue(page([people[2]!]));
        await wrapper.get('[data-test="filter-inactive"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/identity/users', pageParams({ status: 'inactive' }));
        expect(names(wrapper)).toEqual(['Ana']);
    });

    it('creates a user with a confirmed password and reloads the list', async () => {
        const created = make('4', 'Carla', true, 'fleet_manager');
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page([...people, created]));

        await wrapper.get('[data-test="new-user"]').trigger('click');
        await wrapper.get('[data-test="name"]').setValue('Carla');
        await wrapper.get('[data-test="email"]').setValue('carla@erp.test');
        await wrapper.get('[data-test="role-fleet_manager"]').setValue();
        await wrapper.get('[data-test="password"]').setValue(STRONG);
        await wrapper.get('[data-test="password_confirmation"]').setValue(STRONG);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/identity/users', {
            name: 'Carla',
            email: 'carla@erp.test',
            role: 'fleet_manager',
            password: STRONG,
            password_confirmation: STRONG,
        });
        expect(lastToast()).toBe('Usuário Carla criado.');
        expect(names(wrapper)).toContain('Carla');
    });

    it('checks the password locally before calling the API', async () => {
        const wrapper = await mountPage();

        await wrapper.get('[data-test="new-user"]').trigger('click');
        await wrapper.get('[data-test="name"]').setValue('Carla');
        await wrapper.get('[data-test="email"]').setValue('carla@erp.test');
        await wrapper.get('[data-test="role-dispatcher"]').setValue();
        await wrapper.get('[data-test="password"]').setValue('curta');
        await wrapper.get('[data-test="password_confirmation"]').setValue('outra');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="password-error"]').text()).toBe('A senha precisa de pelo menos 12 caracteres.');
        expect(wrapper.get('[data-test="password_confirmation-error"]').text()).toBe('As senhas não conferem.');
    });

    it('edits a user without sending a password when it is left blank', async () => {
        http.put.mockResolvedValue({ data: { data: { ...people[1]!, role: 'fleet_manager', role_label: 'Gestão de frota' } } });
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page(people));

        await wrapper.findAll('[data-test="edit"]')[1]!.trigger('click');
        expect((wrapper.get('[data-test="email"]').element as HTMLInputElement).value).toBe('bruna@erp.test');
        expect(wrapper.find('[data-test="password_confirmation"]').exists()).toBe(false);

        await wrapper.get('[data-test="role-fleet_manager"]').setValue();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.put).toHaveBeenCalledWith('/identity/users/2', { name: 'Bruna', email: 'bruna@erp.test', role: 'fleet_manager' });
        expect(lastToast()).toBe('Usuário Bruna atualizado.');
    });

    it('shows the API reason on the field, such as the last administrator rule', async () => {
        http.put.mockRejectedValue(validationError({ role: ['A empresa precisa de pelo menos um administrador ativo. Promova outro usuário a administrador antes.'] }));
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="edit"]')[0]!.trigger('click');
        await wrapper.get('[data-test="role-dispatcher"]').setValue();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="role-error"]').text()).toContain('pelo menos um administrador ativo');
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('deactivates a user only after confirmation', async () => {
        http.post.mockResolvedValue({ data: { data: make('2', 'Bruna', false) } });
        const wrapper = await mountPage();
        http.get.mockResolvedValue(page([people[0]!, make('2', 'Bruna', false), people[2]!]));

        await wrapper.findAll('[data-test="user-row"]')[1]!.get('[data-test="toggle"]').trigger('click');
        expect(http.post).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/identity/users/2/deactivate');
        expect(lastToast()).toBe('Acesso de Bruna desativado.');
    });

    it('reactivates an inactive user', async () => {
        http.post.mockResolvedValue({ data: { data: make('3', 'Ana', true, 'mechanic_supervisor') } });
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="user-row"]')[2]!.get('[data-test="toggle"]').trigger('click');
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/identity/users/3/reactivate');
        expect(lastToast()).toBe('Acesso de Ana reativado.');
    });

    it('keeps the dialog open with the API reason when deactivation is refused', async () => {
        http.post.mockRejectedValue(validationError({ user: ['Você não pode desativar o próprio acesso.'] }));
        const wrapper = await mountPage();

        await wrapper.findAll('[data-test="user-row"]')[1]!.get('[data-test="toggle"]').trigger('click');
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="confirm-error"]').text()).toBe('Você não pode desativar o próprio acesso.');
    });

    it('shows loading, empty and error states', async () => {
        http.get.mockReturnValue(new Promise(() => {}));
        const loading = mount(UsersView);
        expect(loading.find('[data-test="loading"]').exists()).toBe(true);

        expect((await mountPage([])).find('[data-test="empty"]').exists()).toBe(true);

        http.get.mockRejectedValue(new Error('network'));
        const failing = mount(UsersView);
        await flushPromises();
        expect(failing.find('[data-test="list-error"]').exists()).toBe(true);
    });
});
