import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { signInAs } from '@/tests/auth';
import { page, pageParams } from '@/tests/paging';
import { lastToast } from '@/tests/toast';
import { formatDateTime } from '@/core/utils/format';
import type { TripSummary } from '../../types/trip';
import OperationsView from '../OperationsView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const trip = (id: string, status: TripSummary['status'], destination = 'Ourinhos'): TripSummary => ({
    id,
    status,
    departure_time: '2026-11-20T11:00:00+00:00',
    arrival_time: '2026-11-20T17:30:00+00:00',
    line: { id: 'line-1', origin: 'Curitiba', destination, distance_km: 400 },
    vehicle: { id: 'bus-1', plate: 'ABC1D23' },
    driver: { id: 'driver-1', name: 'João da Silva' },
});

/** O que a API devolve: a escala paginada em /operations/trips e listas vazias nas opções do formulário. */
function serve(trips: TripSummary[]): void {
    http.get.mockImplementation((url: string) => Promise.resolve(url === '/operations/lines' ? { data: { data: [] } } : page(url === '/operations/trips' ? trips : [])));
}

async function mountPage(trips: TripSummary[]): Promise<VueWrapper> {
    serve(trips);
    const wrapper = mount(OperationsView);
    await flushPromises();

    return wrapper;
}

const rows = (wrapper: VueWrapper) => wrapper.findAll('[data-test="trip-row"]');

describe('OperationsView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        http.get.mockReset();
        http.post.mockReset();
    });

    it('lists trips with line, local times, plate, driver and status', async () => {
        const wrapper = await mountPage([trip('1', 'scheduled')]);
        const text = rows(wrapper)[0]!.text();

        expect(text).toContain('Curitiba → Ourinhos');
        expect(text).toContain('400 km');
        expect(text).toContain(formatDateTime('2026-11-20T11:00:00+00:00'));
        expect(text).toContain(formatDateTime('2026-11-20T17:30:00+00:00'));
        expect(text).toContain('ABC1D23');
        expect(text).toContain('João da Silva');
        expect(text).toContain('Agendada');
    });

    it('offers only the actions each status allows', async () => {
        const wrapper = await mountPage([trip('1', 'scheduled'), trip('2', 'in_progress'), trip('3', 'completed'), trip('4', 'canceled')]);

        const actions = rows(wrapper).map((row) => row.findAll('button').map((button) => button.text()));

        expect(actions).toEqual([['Iniciar', 'Cancelar'], ['Concluir'], [], []]);
    });

    it('starts a trip', async () => {
        http.post.mockResolvedValue({ data: { data: { id: '1', status: 'in_progress' } } });
        const wrapper = await mountPage([trip('1', 'scheduled')]);
        serve([trip('1', 'in_progress')]);

        await wrapper.get('[data-test="start"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/operations/trips/1/start');
        expect(rows(wrapper)[0]!.text()).toContain('Em andamento');
        expect(lastToast()).toContain('Curitiba → Ourinhos iniciada.');
    });

    it('completes a trip and says the distance went to the vehicle mileage', async () => {
        http.post.mockResolvedValue({ data: { data: { id: '1', status: 'completed' } } });
        const wrapper = await mountPage([trip('1', 'in_progress')]);
        serve([trip('1', 'completed')]);

        await wrapper.get('[data-test="complete"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/operations/trips/1/complete');
        expect(rows(wrapper)[0]!.text()).toContain('Concluída');
        expect(lastToast()).toContain('400 km somados à quilometragem do veículo ABC1D23');
    });

    it('cancels a trip only after confirmation', async () => {
        http.post.mockResolvedValue({ data: { data: { id: '1', status: 'canceled' } } });
        const wrapper = await mountPage([trip('1', 'scheduled')]);
        serve([trip('1', 'canceled')]);

        await wrapper.get('[data-test="cancel"]').trigger('click');
        expect(http.post).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/operations/trips/1/cancel');
        expect(rows(wrapper)[0]!.text()).toContain('Cancelada');
        expect(wrapper.find('[data-test="confirm-ok"]').exists()).toBe(false);
    });

    it('does not cancel when the confirmation is dismissed', async () => {
        const wrapper = await mountPage([trip('1', 'scheduled')]);

        await wrapper.get('[data-test="cancel"]').trigger('click');
        await wrapper.get('[data-test="confirm-cancel"]').trigger('click');

        expect(http.post).not.toHaveBeenCalled();
        expect(rows(wrapper)[0]!.text()).toContain('Agendada');
    });

    it('shows why a transition was refused', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { status: ['Só é possível iniciar uma viagem agendada.'] } } },
        });
        const wrapper = await mountPage([trip('1', 'scheduled')]);

        await wrapper.get('[data-test="start"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="transition-error"]').text()).toContain('Só é possível iniciar uma viagem agendada.');
        expect(rows(wrapper)[0]!.text()).toContain('Agendada');
    });

    it('shows the empty and error states', async () => {
        expect((await mountPage([])).find('[data-test="empty"]').exists()).toBe(true);

        setActivePinia(createPinia());
        http.get.mockRejectedValue(new Error('network'));
        const failed = mount(OperationsView);
        await flushPromises();

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });

    it('filters by status on the server and pages the result', async () => {
        const wrapper = await mountPage([trip('1', 'scheduled'), trip('2', 'completed')]);

        http.get.mockResolvedValue(page([trip('2', 'completed')], { total: 31, last_page: 3 }));
        await wrapper.get('[data-test="filter-completed"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/operations/trips', pageParams({ status: 'completed' }));
        expect(rows(wrapper)).toHaveLength(1);
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('1 a 15 de 31 viagens');

        await wrapper.get('[data-test="page-3"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/operations/trips', pageParams({ page: 3, status: 'completed' }));
    });

    it('exports the CSV with the filters on screen', async () => {
        const createObjectURL = vi.fn(() => 'blob:manifesto');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL }));
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);

        const wrapper = await mountPage([trip('1', 'completed')]);
        await wrapper.get('[data-test="filter-completed"]').trigger('click');
        await flushPromises();

        const csv = new Blob(['Linha;Placa'], { type: 'text/csv' });
        http.get.mockResolvedValue({ data: csv, headers: { 'content-disposition': 'attachment; filename=manifesto-de-viagens-2026-11-15.csv' } });
        await wrapper.get('[data-test="export-csv"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/operations/trips/export', { params: { status: 'completed' }, responseType: 'blob' });
        expect(createObjectURL).toHaveBeenCalledWith(csv);
        expect(click).toHaveBeenCalledTimes(1);
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:manifesto');
        expect(lastToast()).toContain('Manifesto de viagens exportado');

        click.mockRestore();
    });

    it('says when the export fails', async () => {
        const wrapper = await mountPage([trip('1', 'completed')]);

        http.get.mockRejectedValue(new Error('network'));
        await wrapper.get('[data-test="export-csv"]').trigger('click');
        await flushPromises();

        expect(lastToast()).toContain('Não foi possível exportar as viagens');
        expect(wrapper.get('[data-test="export-csv"]').text()).toBe('Exportar CSV');
    });

    it('opens the scheduling form in the side panel', async () => {
        const wrapper = await mountPage([]);

        await wrapper.get('[data-test="new-trip"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="line_id"]').exists()).toBe(true);
    });

    it('is read-only for a role without operations.manage', async () => {
        signInAs('fleet_manager');
        const wrapper = await mountPage([trip('1', 'scheduled'), trip('2', 'in_progress')]);

        expect(rows(wrapper)).toHaveLength(2);
        expect(wrapper.find('[data-test="new-trip"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="start"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="complete"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="cancel"]').exists()).toBe(false);
    });
});
