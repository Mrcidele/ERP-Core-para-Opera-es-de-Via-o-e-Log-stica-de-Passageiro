import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Line } from '../../types/trip';
import LineForm from '../LineForm.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const existing: Line = {
    id: 'line-1',
    origin: 'Curitiba',
    destination: 'Ourinhos',
    distance_km: 400,
    estimated_duration_minutes: 390,
    stops: [
        { sequence: 1, city_name: 'Ponta Grossa', cumulative_km: 115, estimated_minutes: 105 },
        { sequence: 2, city_name: 'Castro', cumulative_km: 160, estimated_minutes: 150 },
        { sequence: 3, city_name: 'Jaguariaíva', cumulative_km: 240, estimated_minutes: 230 },
    ],
};

const cities = (wrapper: VueWrapper): string[] => wrapper.findAll('[data-test="stop-city"]').map((input) => (input.element as HTMLInputElement).value);

async function fillLine(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-test="origin"]').setValue('Curitiba');
    await wrapper.get('[data-test="destination"]').setValue('Londrina');
    await wrapper.get('[data-test="distance_km"]').setValue(380);
    await wrapper.get('[data-test="estimated_duration_minutes"]').setValue(360);
}

async function addStop(wrapper: VueWrapper, city: string, km: number | '', minutes: number | ''): Promise<void> {
    await wrapper.get('[data-test="add-stop"]').trigger('click');
    const row = wrapper.findAll('[data-test="stop-row"]').at(-1)!;

    await row.get('[data-test="stop-city"]').setValue(city);
    await row.get('[data-test="stop-km"]').setValue(km);
    await row.get('[data-test="stop-minutes"]').setValue(minutes);
}

async function submit(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

describe('LineForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('starts a new line without stops', () => {
        const wrapper = mount(LineForm);

        expect(wrapper.find('[data-test="no-stops"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="route-preview"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="submit"]').text()).toBe('Cadastrar rota');
    });

    it('creates a line sending the stops in the order they are on screen', async () => {
        const saved = { ...existing, id: 'line-9' };
        http.post.mockResolvedValue({ data: { data: saved } });
        const wrapper = mount(LineForm);

        await fillLine(wrapper);
        await addStop(wrapper, 'Ponta Grossa', 115, 105);
        await addStop(wrapper, ' Apucarana ', 325, 305);
        await submit(wrapper);

        expect(http.post).toHaveBeenCalledWith('/operations/lines', {
            origin: 'Curitiba',
            destination: 'Londrina',
            distance_km: 380,
            estimated_duration_minutes: 360,
            stops: [
                { city_name: 'Ponta Grossa', cumulative_km: 115, estimated_minutes: 105 },
                { city_name: 'Apucarana', cumulative_km: 325, estimated_minutes: 305 },
            ],
        });
        expect(wrapper.emitted('saved')).toEqual([[saved]]);
    });

    it('draws each filled stop on the route preview', async () => {
        const wrapper = mount(LineForm);

        await fillLine(wrapper);
        await addStop(wrapper, 'Ponta Grossa', 95, 105);
        await addStop(wrapper, '', '', '');

        const marks = wrapper.get('[data-test="route-preview"]').findAll('[data-test="route-mark"]');

        expect(marks).toHaveLength(1);
        expect(marks[0]!.attributes('style')).toContain('left: 25%');
        expect(marks[0]!.attributes('title')).toBe('Ponta Grossa, km 95');
    });

    it('loads the stops of the line being edited and saves with PUT', async () => {
        http.put.mockResolvedValue({ data: { data: existing } });
        const wrapper = mount(LineForm, { props: { line: existing } });

        expect((wrapper.get('[data-test="origin"]').element as HTMLInputElement).value).toBe('Curitiba');
        expect(cities(wrapper)).toEqual(['Ponta Grossa', 'Castro', 'Jaguariaíva']);
        expect(wrapper.get('[data-test="submit"]').text()).toBe('Salvar alterações');

        await submit(wrapper);

        expect(http.put).toHaveBeenCalledWith('/operations/lines/line-1', expect.objectContaining({ destination: 'Ourinhos' }));
        expect(http.post).not.toHaveBeenCalled();
    });

    it('reorders the stops with the up and down buttons', async () => {
        const wrapper = mount(LineForm, { props: { line: existing } });
        const row = (index: number) => wrapper.findAll('[data-test="stop-row"]')[index]!;

        expect(row(0).get('[data-test="stop-up"]').attributes('disabled')).toBeDefined();
        expect(row(2).get('[data-test="stop-down"]').attributes('disabled')).toBeDefined();

        await row(2).get('[data-test="stop-up"]').trigger('click');
        expect(cities(wrapper)).toEqual(['Ponta Grossa', 'Jaguariaíva', 'Castro']);

        await row(0).get('[data-test="stop-down"]').trigger('click');
        expect(cities(wrapper)).toEqual(['Jaguariaíva', 'Ponta Grossa', 'Castro']);
        // O km acompanha a cidade.
        expect((row(0).get('[data-test="stop-km"]').element as HTMLInputElement).value).toBe('240');
    });

    it('removes a stop', async () => {
        const wrapper = mount(LineForm, { props: { line: existing } });

        await wrapper.findAll('[data-test="stop-remove"]')[1]!.trigger('click');

        expect(cities(wrapper)).toEqual(['Ponta Grossa', 'Jaguariaíva']);
    });

    it('validates before calling the API and points to the stop that is out of order', async () => {
        const wrapper = mount(LineForm, { props: { line: existing } });

        await wrapper.findAll('[data-test="stop-row"]')[2]!.get('[data-test="stop-up"]').trigger('click');
        await submit(wrapper);

        const rows = wrapper.findAll('[data-test="stop-row"]');

        expect(http.put).not.toHaveBeenCalled();
        expect(rows[2]!.get('[data-test="stop-error"]').text()).toBe('Informe um km maior que 240.');
        expect(rows[2]!.get('[data-test="stop-km"]').attributes('aria-invalid')).toBe('true');
        expect(rows[1]!.find('[data-test="stop-error"]').exists()).toBe(false);

        // Reordenar de novo invalida os erros por posição.
        await rows[2]!.get('[data-test="stop-up"]').trigger('click');
        expect(wrapper.find('[data-test="stop-error"]').exists()).toBe(false);
    });

    it('requires the line fields and every field of a stop', async () => {
        const wrapper = mount(LineForm);

        await wrapper.get('[data-test="add-stop"]').trigger('click');
        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="origin-error"]').text()).toBe('Informe a cidade de origem.');
        expect(wrapper.get('[data-test="destination-error"]').text()).toBe('Informe a cidade de destino.');
        expect(wrapper.get('[data-test="distance_km-error"]').text()).toBe('Informe a distância.');
        expect(wrapper.get('[data-test="estimated_duration_minutes-error"]').text()).toBe('Informe a duração.');
        expect(wrapper.get('[data-test="stop-error"]').text()).toBe('Informe a cidade.');
    });

    it('shows on the right place what the API refused', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 422,
                data: { errors: { stops: ['A parada 1 (Castro) deve ficar antes do destino.'], 'stops.0.city_name': ['O campo cidade da parada é obrigatório.'], destination: ['O destino deve ser diferente da origem.'] } },
            },
        });
        const wrapper = mount(LineForm);

        await fillLine(wrapper);
        await addStop(wrapper, 'Castro', 160, 150);
        await submit(wrapper);

        expect(wrapper.get('[data-test="stops-error"]').text()).toContain('deve ficar antes do destino');
        expect(wrapper.get('[data-test="stop-error"]').text()).toBe('O campo cidade da parada é obrigatório.');
        expect(wrapper.get('[data-test="destination-error"]').text()).toBe('O destino deve ser diferente da origem.');
        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    it('shows a general message on unexpected failures', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const wrapper = mount(LineForm);

        await fillLine(wrapper);
        await submit(wrapper);

        expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível cadastrar a rota');
    });

    it('emits cancel', async () => {
        const wrapper = mount(LineForm);

        await wrapper.get('[data-test="cancel"]').trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
    });
});
