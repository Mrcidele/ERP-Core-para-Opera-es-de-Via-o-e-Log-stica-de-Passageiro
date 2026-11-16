import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDateTime } from '@/core/utils/format';
import { signInAs } from '@/tests/auth';
import { page, pageParams } from '@/tests/paging';
import type { AuditLog } from '../../types/auditLog';
import AuditLogsView from '../AuditLogsView.vue';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const logs: AuditLog[] = [
    {
        id: 'log-3',
        action: 'trip.completed',
        auditable_type: 'trip',
        auditable_id: '0193a5b0-7c1e-7000-8000-000000000003',
        old_values: { status: 'in_progress' },
        new_values: { status: 'completed', vehicle_id: 'bus-1', distance_km: 400 },
        ip_address: '10.0.0.7',
        created_at: '2026-11-13T12:00:00+00:00',
        user: { id: 'u2', name: 'Diego Despachante' },
    },
    {
        id: 'log-2',
        action: 'vehicle.deleted',
        auditable_type: 'vehicle',
        auditable_id: '0193a5b0-7c1e-7000-8000-000000000002',
        old_values: { plate: 'ABC1D23', status: 'active' },
        new_values: null,
        ip_address: '10.0.0.5',
        created_at: '2026-11-13T11:00:00+00:00',
        user: { id: 'u1', name: 'Ana Administradora' },
    },
    {
        id: 'log-1',
        action: 'auth.login_failed',
        auditable_type: 'user',
        auditable_id: null,
        old_values: null,
        new_values: { email: 'ninguem@erp.test' },
        ip_address: '203.0.113.9',
        created_at: '2026-11-13T10:00:00+00:00',
        user: null,
    },
];

async function mountView(data: AuditLog[] | Error = logs): Promise<VueWrapper> {
    if (data instanceof Error) {
        http.get.mockRejectedValue(data);
    } else {
        http.get.mockResolvedValue(page(data));
    }

    const wrapper = mount(AuditLogsView);
    await flushPromises();

    return wrapper;
}

const rows = (wrapper: VueWrapper) => wrapper.findAll('[data-test="audit-row"]');
const flat = (text: string): string => text.replace(/\s+/g, ' ');

describe('AuditLogsView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        http.get.mockReset();
    });

    it('lists local date and time, user, action, affected entity and IP', async () => {
        const wrapper = await mountView();
        const first = flat(rows(wrapper)[0]!.text());

        expect(rows(wrapper)).toHaveLength(3);
        expect(first).toContain(formatDateTime('2026-11-13T12:00:00+00:00'));
        expect(first).toContain('Diego Despachante');
        expect(first).toContain('Viagem concluída');
        expect(first).toContain('Viagem 0193a5b0');
        expect(first).toContain('10.0.0.7');
        expect(http.get).toHaveBeenCalledWith('/audit-logs', pageParams());
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('3 registros');
    });

    it('shows a record without a user and without an entity id', async () => {
        const wrapper = await mountView();
        const last = flat(rows(wrapper)[2]!.text());

        expect(last).toContain('Sem usuário');
        expect(last).toContain('Login recusado');
        expect(last).toContain('Usuário');
        expect(last).toContain('203.0.113.9');
    });

    it('opens the side panel with the old and new values as formatted JSON, side by side', async () => {
        const wrapper = await mountView();

        await rows(wrapper)[0]!.get('[data-test="details"]').trigger('click');

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.attributes('aria-label')).toBe('Viagem concluída');
        expect(dialog.classes()).toContain('max-w-3xl');
        expect(dialog.text()).toContain('por Diego Despachante');
        expect(wrapper.get('[data-test="old-values"] pre').text()).toBe(JSON.stringify({ status: 'in_progress' }, null, 2));
        expect(wrapper.get('[data-test="new-values"] pre').text()).toBe(JSON.stringify({ status: 'completed', vehicle_id: 'bus-1', distance_km: 400 }, null, 2));
        expect(wrapper.findAll('[data-test="changed-keys"] span').map((key) => key.text())).toEqual(['status', 'vehicle_id', 'distance_km']);
        expect(flat(wrapper.get('[data-test="audit-meta"]').text())).toContain('0193a5b0-7c1e-7000-8000-000000000003');
    });

    it('explains a missing side instead of showing an empty box', async () => {
        const wrapper = await mountView();

        await rows(wrapper)[1]!.get('[data-test="details"]').trigger('click');
        expect(wrapper.find('[data-test="new-values"] pre').exists()).toBe(false);
        expect(wrapper.get('[data-test="new-values"]').text()).toContain('o registro foi removido');
        expect(wrapper.get('[data-test="old-values"] pre').text()).toContain('"plate": "ABC1D23"');

        await wrapper.get('[data-test="close-details"]').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await rows(wrapper)[2]!.get('[data-test="details"]').trigger('click');
        expect(wrapper.get('[data-test="old-values"]').text()).toContain('o registro foi criado');
        expect(wrapper.get('[role="dialog"]').text()).toContain('por ninguém autenticado');
    });

    it('searches on the server and pages the trail', async () => {
        const wrapper = await mountView();

        http.get.mockResolvedValue(page([logs[2]!], { total: 40, last_page: 3 }));
        await wrapper.get('[data-test="search"]').setValue('203.0.113');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/audit-logs', pageParams({ search: '203.0.113' }));
        expect(rows(wrapper).map((row) => row.get('[data-test="action"]').text())).toEqual(['Login recusado']);
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('1 a 15 de 40 registros');

        await wrapper.get('[data-test="page-next"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/audit-logs', pageParams({ page: 2, search: '203.0.113' }));

        http.get.mockResolvedValue(page([]));
        await wrapper.get('[data-test="search"]').setValue('nada parecido');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();

        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);
    });

    it('reloads on demand', async () => {
        const wrapper = await mountView();

        await wrapper.get('[data-test="refresh"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenCalledTimes(2);
    });

    it('shows the empty and error states', async () => {
        expect((await mountView([])).get('[data-test="empty"]').text()).toContain('Nenhum registro de auditoria');

        setActivePinia(createPinia());
        const failed = await mountView(new Error('network'));

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });
});
