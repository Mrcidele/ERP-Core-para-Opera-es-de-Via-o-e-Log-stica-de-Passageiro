import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '@/modules/auth/stores/authStore';
import { signInAs } from '@/tests/auth';
import { page, pageParams } from '@/tests/paging';
import { lastToast } from '@/tests/toast';
import type { MaintenanceOrderSummary } from '../../types/maintenance';
import MaintenanceView from '../MaintenanceView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const order = (id: string, status: MaintenanceOrderSummary['status'], type: MaintenanceOrderSummary['type'] = 'corrective'): MaintenanceOrderSummary => ({
    id,
    type,
    status,
    description: `Serviço ${id}`,
    cost_cents: status === 'completed' ? 45_090 : 0,
    opened_at: '2026-11-13T10:00:00+00:00',
    completed_at: status === 'completed' ? '2026-11-14T16:30:00+00:00' : null,
    vehicle: { id: `bus-${id}`, plate: `ABC1D2${id}` },
    mechanic: { id: 'mechanic-1', name: 'Maria Souza' },
});

const vehicles = [{ id: 'bus-9', plate: 'XYZ9876', chassis: '9BWZZZ377VT004251', capacity: 46, class_type: 'executivo', status: 'active', current_mileage: 1000 }];
const mechanics = [{ id: 'mechanic-1', name: 'Maria Souza' }];

let orders: MaintenanceOrderSummary[] = [];

async function mountPage(initial: MaintenanceOrderSummary[]): Promise<VueWrapper> {
    orders = initial;
    http.get.mockImplementation((url: string) =>
        // Ordens e veículos chegam paginados; os mecânicos, em lista simples.
        Promise.resolve(url === '/maintenance/mechanics' ? { data: { data: mechanics } } : page<unknown>(url === '/maintenance/orders' ? orders : vehicles)),
    );
    const wrapper = mount(MaintenanceView);
    await flushPromises();

    return wrapper;
}

const rows = (wrapper: VueWrapper) => wrapper.findAll('[data-test="order-row"]');

describe('MaintenanceView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('lists orders with plate, type badge, mechanic, status and cost', async () => {
        const wrapper = await mountPage([order('1', 'open', 'preventive'), order('2', 'completed')]);

        expect(rows(wrapper)[0]!.text()).toContain('ABC1D21');
        expect(rows(wrapper)[0]!.get('[data-test="type-badge"]').text()).toBe('Preventiva');
        expect(rows(wrapper)[0]!.text()).toContain('Maria Souza');
        expect(rows(wrapper)[0]!.text()).toContain('Aberta');
        expect(rows(wrapper)[0]!.get('[data-test="cost"]').text()).toBe('');

        expect(rows(wrapper)[1]!.get('[data-test="type-badge"]').text()).toBe('Corretiva');
        expect(rows(wrapper)[1]!.get('[data-test="cost"]').text().replace(/\s/g, ' ')).toBe('R$ 450,90');
    });

    it('offers actions only for pending orders', async () => {
        const wrapper = await mountPage([order('1', 'open'), order('2', 'completed'), order('3', 'canceled')]);

        expect(rows(wrapper).map((row) => row.findAll('button').map((button) => button.text()))).toEqual([['Concluir', 'Cancelar'], [], []]);
    });

    it('filters by status on the server', async () => {
        const wrapper = await mountPage([order('1', 'open'), order('2', 'completed'), order('3', 'completed')]);

        orders = [order('1', 'open')];
        await wrapper.get('[data-test="filter-open"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/maintenance/orders', pageParams({ status: 'open' }));
        expect(rows(wrapper)).toHaveLength(1);
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('1 ordem');

        orders = [];
        await wrapper.get('[data-test="filter-canceled"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);
    });

    it('exports the CSV with the filters on screen', async () => {
        const createObjectURL = vi.fn(() => 'blob:ordens');
        vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL: vi.fn() }));
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
        const wrapper = await mountPage([order('1', 'completed')]);

        await wrapper.get('[data-test="filter-completed"]').trigger('click');
        await flushPromises();

        http.get.mockResolvedValue({ data: new Blob(['Placa;Custo']), headers: {} });
        await wrapper.get('[data-test="export-csv"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/maintenance/orders/export', { params: { status: 'completed' }, responseType: 'blob' });
        expect(click).toHaveBeenCalledTimes(1);
        expect(lastToast()).toContain('Relatório de ordens de serviço exportado');

        click.mockRestore();
    });

    it('opens an order from the side panel and reloads the list', async () => {
        const wrapper = await mountPage([]);
        http.post.mockImplementation(() => {
            orders = [order('9', 'open')];

            return Promise.resolve({ data: { data: { id: '9', status: 'open', cost_cents: 0 } } });
        });

        await wrapper.get('[data-test="new-order"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-test="vehicle_id"]').setValue('bus-9');
        await wrapper.get('[data-test="type-preventive"]').setValue();
        await wrapper.get('[data-test="mechanic_id"]').setValue('mechanic-1');
        await wrapper.get('[data-test="description"]').setValue('Troca de óleo e filtros');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/maintenance/orders', {
            vehicle_id: 'bus-9',
            mechanic_id: 'mechanic-1',
            type: 'preventive',
            description: 'Troca de óleo e filtros',
        });
        expect(rows(wrapper)).toHaveLength(1);
        expect(lastToast()).toContain('Ordem de serviço aberta');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('validates the opening form before calling the API', async () => {
        const wrapper = await mountPage([]);

        await wrapper.get('[data-test="new-order"]').trigger('click');
        await flushPromises();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).not.toHaveBeenCalled();
        for (const field of ['vehicle_id', 'type', 'mechanic_id', 'description']) {
            expect(wrapper.find(`[data-test="${field}-error"]`).exists()).toBe(true);
        }
    });

    it('shows on the right field why the API refused the opening', async () => {
        const wrapper = await mountPage([]);
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { mechanic_id: ['O funcionário informado não existe, não é mecânico ou não está ativo.'] } } },
        });

        await wrapper.get('[data-test="new-order"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-test="vehicle_id"]').setValue('bus-9');
        await wrapper.get('[data-test="type-corrective"]').setValue();
        await wrapper.get('[data-test="mechanic_id"]').setValue('mechanic-1');
        await wrapper.get('[data-test="description"]').setValue('Reparo no ar-condicionado');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="mechanic_id-error"]').text()).toContain('não é mecânico');
    });

    it('completes an order with the cost typed in reais', async () => {
        const wrapper = await mountPage([order('1', 'open')]);
        http.patch.mockImplementation(() => {
            orders = [order('1', 'completed')];

            return Promise.resolve({ data: { data: { id: '1', status: 'completed', cost_cents: 45_090 } } });
        });

        await rows(wrapper)[0]!.get('[data-test="complete"]').trigger('click');
        await wrapper.get('input[data-test="cost"]').setValue('450,90');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.patch).toHaveBeenCalledWith('/maintenance/orders/1', { status: 'completed', cost_cents: 45_090 });
        expect(rows(wrapper)[0]!.text()).toContain('Concluída');
        expect(lastToast().replace(/\s/g, ' ')).toContain('concluída por R$ 450,90');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('rejects an invalid cost before calling the API', async () => {
        const wrapper = await mountPage([order('1', 'open')]);

        await rows(wrapper)[0]!.get('[data-test="complete"]').trigger('click');
        await wrapper.get('input[data-test="cost"]').setValue('10.5');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.patch).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="cost-error"]').text()).toContain('como 450,90');
    });

    it('cancels an order only after confirmation', async () => {
        const wrapper = await mountPage([order('1', 'open')]);
        http.patch.mockImplementation(() => {
            orders = [order('1', 'canceled')];

            return Promise.resolve({ data: { data: { id: '1', status: 'canceled', cost_cents: 0 } } });
        });

        await rows(wrapper)[0]!.get('[data-test="cancel"]').trigger('click');
        expect(http.patch).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.patch).toHaveBeenCalledWith('/maintenance/orders/1', { status: 'canceled' });
        expect(rows(wrapper)[0]!.text()).toContain('Cancelada');
        expect(lastToast()).toContain('cancelada');
    });

    it('shows the empty and error states', async () => {
        expect((await mountPage([])).find('[data-test="empty"]').exists()).toBe(true);

        setActivePinia(createPinia());
        http.get.mockRejectedValue(new Error('network'));
        const failed = mount(MaintenanceView);
        await flushPromises();

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });

    it('lets the workshop supervisor manage orders', async () => {
        signInAs('mechanic_supervisor');
        const wrapper = await mountPage([order('1', 'open')]);

        expect(wrapper.find('[data-test="new-order"]').exists()).toBe(true);
        expect(rows(wrapper)[0]!.findAll('button').map((button) => button.text())).toEqual(['Concluir', 'Cancelar']);
    });

    it('is read-only without maintenance.manage', async () => {
        useAuthStore().setSession({
            token: 'test-token',
            user: { id: 'user-2', name: 'Só Consulta', email: 'consulta@erp.test', role: 'dispatcher' },
            permissions: ['maintenance.view'],
        });
        const wrapper = await mountPage([order('1', 'open')]);

        expect(rows(wrapper)).toHaveLength(1);
        expect(wrapper.find('[data-test="new-order"]').exists()).toBe(false);
        expect(rows(wrapper)[0]!.findAll('button')).toHaveLength(0);
    });
});
