import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { signInAs } from '@/tests/auth';
import { lastToast } from '@/tests/toast';
import type { Employee } from '../../types/employee';
import CrewView from '../CrewView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const employee = (id: string, name: string, role: Employee['role'], status: Employee['status'], expiration: string | null = null): Employee => ({
    id,
    name,
    cpf: id === '1' ? '52998224725' : '11144477735',
    role,
    status,
    cnh_number: role === 'driver' ? '12345678901' : null,
    cnh_category: role === 'driver' ? 'D' : null,
    cnh_expiration_date: expiration,
});

const crew = [
    employee('1', 'Ana Motorista', 'driver', 'active', '2030-01-01'),
    employee('2', 'Bruno Vencido', 'driver', 'on_leave', '2026-11-10'),
    employee('3', 'Carla Mecânica', 'mechanic', 'active'),
];

async function mountView(employees: Employee[] = crew): Promise<VueWrapper> {
    http.get.mockResolvedValue(page(employees));
    const wrapper = mount(CrewView);
    await flushPromises();

    return wrapper;
}

const names = (wrapper: VueWrapper): string[] => wrapper.findAll('[data-test="employee-row"] td:first-child').map((cell) => cell.text());

describe('CrewView', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 10, 13, 10, 0));
        setActivePinia(createPinia());
        signInAs('admin');
        http.get.mockReset();
        http.post.mockReset();
    });

    afterEach(() => vi.useRealTimers());

    it('lists the employees with masked CPF, role, license and status', async () => {
        const wrapper = await mountView();
        const rows = wrapper.findAll('[data-test="employee-row"]');

        expect(http.get).toHaveBeenCalledWith('/crew/employees', pageParams());
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('3 funcionários');
        expect(rows[0]!.text()).toContain('Ana Motorista');
        expect(rows[0]!.text()).toContain('529.982.247-25');
        expect(rows[0]!.text()).toContain('Motorista');
        expect(rows[0]!.text()).toContain('01/01/2030');
        expect(rows[0]!.get('[data-test="status-badge"]').text()).toBe('Ativo');
        expect(rows[2]!.text()).toContain('Mecânico');
        expect(rows[2]!.text()).toContain('Não se aplica');
    });

    it('flags an expired license and one that expires within 30 days', async () => {
        const wrapper = await mountView([
            employee('1', 'Ana Em Dia', 'driver', 'active', '2026-12-14'),
            employee('2', 'Bruno Vencido', 'driver', 'active', '2026-11-13'),
            employee('3', 'Caio Vencendo', 'driver', 'active', '2026-11-23'),
        ]);
        const rows = wrapper.findAll('[data-test="employee-row"]');

        expect(rows[0]!.find('[data-test="license-ok"]').exists()).toBe(true);
        expect(rows[1]!.find('[data-test="license-expired"]').exists()).toBe(true);
        expect(rows[1]!.text()).toContain('Vencida');
        expect(rows[2]!.find('[data-test="license-expiring"]').exists()).toBe(true);
        expect(rows[2]!.text()).toContain('Vence em 10 dias');
    });

    it('filters by status and searches by name or CPF', async () => {
        const wrapper = await mountView();

        http.get.mockResolvedValue(page([crew[0]!, crew[2]!]));
        await wrapper.get('[data-test="filter-active"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/crew/employees', pageParams({ status: 'active' }));
        expect(names(wrapper)).toEqual(['Ana Motorista', 'Carla Mecânica']);

        // Enter envia a busca na hora, sem esperar a pausa da digitação.
        http.get.mockResolvedValue(page([crew[0]!]));
        await wrapper.get('[data-test="search"]').setValue('529.982');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/crew/employees', pageParams({ status: 'active', search: '529.982' }));
        expect(names(wrapper)).toEqual(['Ana Motorista']);

        http.get.mockResolvedValue(page([]));
        await wrapper.get('[data-test="search"]').setValue('ninguém');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();

        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);
    });

    it('registers an employee from the side panel, reloads the list and confirms with a toast', async () => {
        const created = employee('4', 'Beto Novo', 'admin', 'active');
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = await mountView();
        http.get.mockResolvedValue(page([crew[0]!, created, crew[1]!, crew[2]!]));

        await wrapper.get('[data-test="new-employee"]').trigger('click');
        await wrapper.get('[data-test="name"]').setValue('Beto Novo');
        await wrapper.get('[data-test="cpf"]').setValue('11144477735');
        await wrapper.get('[data-test="role-admin"]').setValue();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(names(wrapper)).toEqual(['Ana Motorista', 'Beto Novo', 'Bruno Vencido', 'Carla Mecânica']);
        expect(lastToast()).toBe('Beto Novo cadastrado como administrativo.');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it.each(['dispatcher', 'fleet_manager'] as const)('is read-only for %s, who only has crew.view', async (role) => {
        signInAs(role);
        const wrapper = await mountView();

        expect(names(wrapper)).toHaveLength(3);
        expect(wrapper.find('[data-test="new-employee"]').exists()).toBe(false);
    });

    it('shows the empty and error states', async () => {
        const empty = await mountView([]);
        expect(empty.get('[data-test="empty"]').text()).toContain('Nenhum funcionário cadastrado');

        setActivePinia(createPinia());
        http.get.mockRejectedValue(new Error('network'));
        const failed = mount(CrewView);
        await flushPromises();

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });
});
