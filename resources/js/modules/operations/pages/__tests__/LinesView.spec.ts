import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { signInAs } from '@/tests/auth';
import { lastToast } from '@/tests/toast';
import type { Line } from '../../types/trip';
import LinesView from '../LinesView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const ourinhos: Line = {
    id: 'line-1',
    origin: 'Curitiba',
    destination: 'Ourinhos',
    distance_km: 400,
    estimated_duration_minutes: 390,
    stops: [
        { sequence: 1, city_name: 'Ponta Grossa', cumulative_km: 100, estimated_minutes: 105 },
        { sequence: 2, city_name: 'Castro', cumulative_km: 160, estimated_minutes: 150 },
    ],
};
const pontaGrossa: Line = { id: 'line-2', origin: 'Curitiba', destination: 'Ponta Grossa', distance_km: 115, estimated_duration_minutes: 120, stops: [] };
const florianopolis: Line = {
    id: 'line-3',
    origin: 'Curitiba',
    destination: 'Florianópolis',
    distance_km: 300,
    estimated_duration_minutes: 285,
    stops: [{ sequence: 1, city_name: 'Joinville', cumulative_km: 130, estimated_minutes: 120 }],
};

async function mountView(lines: Line[] = [florianopolis, ourinhos, pontaGrossa]): Promise<VueWrapper> {
    http.get.mockResolvedValue({ data: { data: lines } });
    const wrapper = mount(LinesView);
    await flushPromises();

    return wrapper;
}

const rows = (wrapper: VueWrapper) => wrapper.findAll('[data-test="line-row"]');
const names = (wrapper: VueWrapper): string[] => wrapper.findAll('[data-test="line-name"]').map((cell) => cell.text());
const flat = (text: string): string => text.replace(/\s+/g, ' ').trim();

describe('LinesView', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        signInAs('admin');
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    afterEach(() => vi.useRealTimers());

    it('lists each line with its stops, distance and duration', async () => {
        const wrapper = await mountView();

        expect(http.get).toHaveBeenCalledWith('/operations/lines');
        expect(names(wrapper)).toEqual(['Curitiba → Florianópolis', 'Curitiba → Ourinhos', 'Curitiba → Ponta Grossa']);
        expect(flat(rows(wrapper)[1]!.get('[data-test="line-stops"]').text())).toBe('2 paradas: Ponta Grossa, Castro');
        expect(flat(rows(wrapper)[0]!.get('[data-test="line-stops"]').text())).toBe('1 parada: Joinville');
        expect(rows(wrapper)[2]!.get('[data-test="line-stops"]').text()).toBe('Direta, sem paradas');
        expect(rows(wrapper)[1]!.text()).toContain('400 km');
        expect(rows(wrapper)[1]!.text()).toContain('6h 30min');
        expect(wrapper.get('[data-test="count"]').text()).toBe('3 rotas');
    });

    it('places each stop on the itinerary in proportion to its km', async () => {
        const wrapper = await mountView();
        const marks = rows(wrapper)[1]!.findAll('[data-test="route-mark"]');

        expect(marks.map((mark) => mark.attributes('style'))).toEqual(['left: 25%;', 'left: 40%;']);
        expect(rows(wrapper)[2]!.findAll('[data-test="route-mark"]')).toHaveLength(0);
    });

    it('searches by origin, destination or stop city', async () => {
        vi.useFakeTimers();
        const wrapper = await mountView();

        await wrapper.get('[data-test="search"]').setValue('ponta');
        await vi.advanceTimersByTimeAsync(200);

        expect(names(wrapper)).toEqual(['Curitiba → Ourinhos', 'Curitiba → Ponta Grossa']);
        expect(wrapper.get('[data-test="count"]').text()).toBe('2 de 3 rotas');
        expect(http.get).toHaveBeenCalledTimes(1);

        await wrapper.get('[data-test="search"]').setValue('Manaus');
        await vi.advanceTimersByTimeAsync(200);
        expect(wrapper.find('[data-test="no-results"]').exists()).toBe(true);

        await wrapper.get('[data-test="clear-filters"]').trigger('click');
        expect(rows(wrapper)).toHaveLength(3);
    });

    it('registers a line from the side panel and confirms with a toast', async () => {
        const created: Line = { id: 'line-9', origin: 'Curitiba', destination: 'Londrina', distance_km: 380, estimated_duration_minutes: 360, stops: [] };
        http.post.mockResolvedValue({ data: { data: created } });
        const wrapper = await mountView();

        await wrapper.get('[data-test="new-line"]').trigger('click');
        await wrapper.get('[data-test="origin"]').setValue('Curitiba');
        await wrapper.get('[data-test="destination"]').setValue('Londrina');
        await wrapper.get('[data-test="distance_km"]').setValue(380);
        await wrapper.get('[data-test="estimated_duration_minutes"]').setValue(360);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(names(wrapper)).toEqual(['Curitiba → Florianópolis', 'Curitiba → Londrina', 'Curitiba → Ourinhos', 'Curitiba → Ponta Grossa']);
        expect(lastToast()).toBe('Rota Curitiba → Londrina cadastrada.');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('edits a line with its stops loaded in the form', async () => {
        http.put.mockResolvedValue({ data: { data: { ...ourinhos, distance_km: 410 } } });
        const wrapper = await mountView();

        await rows(wrapper)[1]!.get('[data-test="edit"]').trigger('click');

        expect(wrapper.get('[role="dialog"]').attributes('aria-label')).toBe('Editar Curitiba → Ourinhos');
        expect(wrapper.findAll('[data-test="stop-row"]')).toHaveLength(2);

        await wrapper.get('[data-test="distance_km"]').setValue(410);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.put).toHaveBeenCalledWith('/operations/lines/line-1', expect.objectContaining({ distance_km: 410 }));
        expect(rows(wrapper)[1]!.text()).toContain('410 km');
        expect(lastToast()).toBe('Rota Curitiba → Ourinhos atualizada.');
    });

    it('deletes a line only after confirmation', async () => {
        http.delete.mockResolvedValue({});
        const wrapper = await mountView();

        await rows(wrapper)[2]!.get('[data-test="remove"]').trigger('click');
        expect(http.delete).not.toHaveBeenCalled();

        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/operations/lines/line-2');
        expect(names(wrapper)).toEqual(['Curitiba → Florianópolis', 'Curitiba → Ourinhos']);
        expect(lastToast()).toBe('Rota Curitiba → Ponta Grossa excluída.');
    });

    it('keeps the line and explains when it has scheduled or running trips', async () => {
        http.delete.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { line: ['Esta rota tem viagens agendadas ou em andamento. Conclua ou cancele as viagens antes de excluí-la.'] } } },
        });
        const wrapper = await mountView();

        await rows(wrapper)[1]!.get('[data-test="remove"]').trigger('click');
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="confirm-error"]').text()).toContain('tem viagens agendadas ou em andamento');
        expect(rows(wrapper)).toHaveLength(3);

        // O motivo não fica para a próxima rota que o usuário tentar excluir.
        await wrapper.get('[data-test="confirm-cancel"]').trigger('click');
        await rows(wrapper)[2]!.get('[data-test="remove"]').trigger('click');
        expect(wrapper.find('[data-test="confirm-error"]').exists()).toBe(false);
    });

    it.each(['fleet_manager'] as const)('is read-only for %s, who only has operations.view', async (role) => {
        signInAs(role);
        const wrapper = await mountView();

        expect(rows(wrapper)).toHaveLength(3);
        expect(wrapper.find('[data-test="new-line"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="edit"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="remove"]').exists()).toBe(false);
    });

    it('shows the empty and error states', async () => {
        const empty = await mountView([]);
        expect(empty.get('[data-test="empty"]').text()).toContain('Nenhuma rota cadastrada');

        setActivePinia(createPinia());
        signInAs('admin');
        http.get.mockRejectedValue(new Error('network'));
        const failed = mount(LinesView);
        await flushPromises();

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });
});
