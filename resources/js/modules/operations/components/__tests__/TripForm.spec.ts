import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page } from '@/tests/paging';
import type { TripPrefill } from '../../types/timeline';
import type { Trip } from '../../types/trip';
import TripForm from '../TripForm.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const lines = [{ id: 'line-1', origin: 'Curitiba', destination: 'Ourinhos', distance_km: 400, estimated_duration_minutes: 390 }];

const vehicle = (id: string, plate: string, status: string) => ({
    id,
    plate,
    chassis: '9BWZZZ377VT004251',
    capacity: 46,
    class_type: 'executivo',
    status,
    current_mileage: 1000,
});

const employee = (id: string, name: string, role: string, status: string) => ({
    id,
    name,
    cpf: '52998224725',
    role,
    status,
    cnh_number: role === 'driver' ? '12345678901' : null,
    cnh_category: role === 'driver' ? 'D' : null,
    cnh_expiration_date: role === 'driver' ? '2099-01-01' : null,
});

const options: Record<string, unknown[]> = {
    '/operations/lines': lines,
    '/fleet/vehicles': [vehicle('bus-1', 'ABC1D23', 'active'), vehicle('bus-2', 'XYZ9876', 'maintenance')],
    '/crew/employees': [
        employee('driver-1', 'João da Silva', 'driver', 'active'),
        employee('driver-2', 'Pedro Afastado', 'driver', 'on_leave'),
        employee('mechanic-1', 'Maria Souza', 'mechanic', 'active'),
    ],
};

const scheduled: Trip = {
    id: 'trip-1',
    line_id: 'line-1',
    vehicle_id: 'bus-1',
    driver_id: 'driver-1',
    departure_time: '2099-11-20T11:00:00+00:00',
    arrival_time: '2099-11-20T17:30:00+00:00',
    status: 'scheduled',
};

async function mountForm(overrides: Record<string, unknown[]> = {}, initial: TripPrefill = {}): Promise<VueWrapper> {
    // Linhas vêm em lista simples; veículos e funcionários, paginados e filtrados por status no servidor.
    http.get.mockImplementation((url: string, config?: { params?: { status?: string } }) => {
        const list = (overrides[url] ?? options[url] ?? []) as { status?: string }[];
        const status = config?.params?.status;

        return Promise.resolve(url === '/operations/lines' ? { data: { data: list } } : page(list.filter((item) => status === undefined || item.status === status)));
    });
    const wrapper = mount(TripForm, { props: { initial } });
    await flushPromises();

    return wrapper;
}

async function fill(wrapper: VueWrapper, departure = '2099-11-20T08:00', arrival = '2099-11-20T14:30'): Promise<void> {
    await wrapper.get('[data-test="line_id"]').setValue('line-1');
    await wrapper.get('[data-test="vehicle_id"]').setValue('bus-1');
    await wrapper.get('[data-test="driver_id"]').setValue('driver-1');
    await wrapper.get('[data-test="departure_time"]').setValue(departure);
    await wrapper.get('[data-test="arrival_time"]').setValue(arrival);
}

async function submit(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

const optionTexts = (wrapper: VueWrapper, field: string): string[] =>
    wrapper
        .findAll(`[data-test="${field}"] option`)
        .slice(1)
        .map((option) => option.text());

describe('TripForm', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
        http.post.mockReset();
    });

    it('loads lines, active vehicles and active drivers', async () => {
        const wrapper = await mountForm();

        expect(http.get.mock.calls.map(([url]) => url).sort()).toEqual(['/crew/employees', '/fleet/vehicles', '/operations/lines']);
        expect(optionTexts(wrapper, 'line_id')).toEqual(['Curitiba → Ourinhos']);
        expect(optionTexts(wrapper, 'vehicle_id')).toEqual(['ABC1D23 (Executivo, 46 lugares)']);
        expect(optionTexts(wrapper, 'driver_id')).toEqual(['João da Silva (CNH D)']);
    });

    it('shows the distance and estimated duration of the selected line', async () => {
        const wrapper = await mountForm();

        await wrapper.get('[data-test="line_id"]').setValue('line-1');

        expect(wrapper.get('[data-test="line-hint"]').text()).toContain('400 km, duração estimada de 6h 30min');
    });

    it('says when there is nothing to choose', async () => {
        const wrapper = await mountForm({ '/operations/lines': [], '/fleet/vehicles': [], '/crew/employees': [] });

        expect(wrapper.find('[data-test="no-lines"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="no-vehicles"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="no-drivers"]').exists()).toBe(true);
    });

    it('shows an error state with retry when the options fail to load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const wrapper = mount(TripForm);
        await flushPromises();

        expect(wrapper.find('[data-test="options-error"]').exists()).toBe(true);
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('shows client validation errors and does not call the API', async () => {
        const wrapper = await mountForm();

        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        for (const field of ['line_id', 'vehicle_id', 'driver_id', 'departure_time', 'arrival_time']) {
            expect(wrapper.find(`[data-test="${field}-error"]`).exists()).toBe(true);
        }
    });

    it('rejects an arrival before the departure without calling the API', async () => {
        const wrapper = await mountForm();

        await fill(wrapper, '2099-11-20T08:00', '2099-11-20T07:00');
        await submit(wrapper);

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="arrival_time-error"]').text()).toContain('depois da partida');
    });

    it('posts the trip with ISO 8601 UTC times and emits it', async () => {
        http.post.mockResolvedValue({ data: { data: scheduled } });
        const wrapper = await mountForm();

        await fill(wrapper);
        await submit(wrapper);

        expect(http.post).toHaveBeenCalledWith('/operations/trips', {
            line_id: 'line-1',
            vehicle_id: 'bus-1',
            driver_id: 'driver-1',
            departure_time: new Date(2099, 10, 20, 8, 0).toISOString(),
            arrival_time: new Date(2099, 10, 20, 14, 30).toISOString(),
        });
        expect(wrapper.emitted('scheduled')).toEqual([[scheduled]]);
    });

    it('shows a schedule conflict returned by the API on the right field', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { driver_id: ['O motorista já tem outra viagem nesse período.'] } } },
        });
        const wrapper = await mountForm();

        await fill(wrapper);
        await submit(wrapper);

        expect(wrapper.get('[data-test="driver_id-error"]').text()).toContain('já tem outra viagem');
        expect(wrapper.emitted('scheduled')).toBeUndefined();
    });

    it('shows a generic message on unexpected failures', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const wrapper = await mountForm();

        await fill(wrapper);
        await submit(wrapper);

        expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível agendar');
    });

    it('starts with the vehicle, the driver and the departure chosen on the schedule grid', async () => {
        const wrapper = await mountForm({}, { vehicle_id: 'bus-1', driver_id: 'driver-1', departure_time: '2099-11-20T08:00' });

        expect((wrapper.get('[data-test="vehicle_id"]').element as HTMLSelectElement).value).toBe('bus-1');
        expect((wrapper.get('[data-test="driver_id"]').element as HTMLSelectElement).value).toBe('driver-1');
        expect((wrapper.get('[data-test="departure_time"]').element as HTMLInputElement).value).toBe('2099-11-20T08:00');
        expect((wrapper.get('[data-test="line_id"]').element as HTMLSelectElement).value).toBe('');
        expect((wrapper.get('[data-test="arrival_time"]').element as HTMLInputElement).value).toBe('');
    });
});
