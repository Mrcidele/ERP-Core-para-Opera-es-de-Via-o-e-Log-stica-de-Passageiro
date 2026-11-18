import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDateTime } from '@/core/utils/format';
import { signInAs } from '@/tests/auth';
import { page, pageParams } from '@/tests/paging';
import { currentToasts, lastToast } from '@/tests/toast';
import type { FuelLog } from '../../types/fuelLog';
import FuelView from '../FuelView.vue';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const log = (id: string, overrides: Partial<FuelLog> = {}): FuelLog => ({
    id,
    odometer_km: 100_640,
    liters_milliliters: 200_000,
    total_cost_cents: 119_800,
    station_name: 'Posto Graal Ourinhos',
    fueled_at: '2026-11-12T11:00:00+00:00',
    distance_km: 640,
    km_per_liter: 3.2,
    low_efficiency_alert: false,
    min_meters_per_liter: 2_000,
    vehicle: { id: 'truck-1', plate: 'ABC1D23' },
    recorded_by: { id: 'user-1', name: 'Fernanda Frota' },
    ...overrides,
});

const normal = log('1');
const thirsty = log('2', { odometer_km: 100_340, distance_km: 340, liters_milliliters: 200_000, km_per_liter: 1.7, low_efficiency_alert: true, min_meters_per_liter: 2_200, vehicle: { id: 'truck-2', plate: 'XYZ9K88' } });
const first = log('3', { distance_km: null, km_per_liter: null, liters_milliliters: 350_500, total_cost_cents: 209_950 });
const parked = log('4', { distance_km: 0, km_per_liter: null });

const vehicles = [
    { id: 'truck-1', plate: 'ABC1D23', chassis: '9BWZZZ377VT004251', payload_kg: 32_000, vehicle_type: 'cavalo_mecanico', vehicle_type_label: 'Cavalo mecânico', model: 'Volvo FH 540', min_meters_per_liter: null, effective_min_meters_per_liter: 2_000, status: 'active', current_mileage: 100_000, next_preventive_maintenance_km: null, needs_preventive_maintenance: false },
    { id: 'truck-2', plate: 'XYZ9K88', chassis: '9BWZZZ377VT004252', payload_kg: 18_000, vehicle_type: 'bitruck', vehicle_type_label: 'Bitruck', model: null, min_meters_per_liter: 2_200, effective_min_meters_per_liter: 2_200, status: 'maintenance', current_mileage: 55_000, next_preventive_maintenance_km: null, needs_preventive_maintenance: false },
];

let logs: FuelLog[] = [];
let monthFuel = { month_start: '2026-11-01T00:00:00+00:00', month_cost_cents: 2_150_590, month_liters_milliliters: 3_505_500, month_logs: 12 };

/** O que a API devolve: abastecimentos paginados, veículos paginados e o resumo do painel. */
function serve(): void {
    http.get.mockImplementation((url: string) =>
        Promise.resolve(url === '/dashboard/summary' ? { data: { data: { fuel: monthFuel } } } : page<unknown>(url === '/fuel/logs' ? logs : vehicles)),
    );
}

async function mountView(initial: FuelLog[] = [normal, thirsty, first, parked]): Promise<VueWrapper> {
    logs = initial;
    serve();
    const wrapper = mount(FuelView);
    await flushPromises();

    return wrapper;
}

const rows = (wrapper: VueWrapper) => wrapper.findAll('[data-test="fuel-row"]');
const flat = (text: string): string => text.replace(/\s+/g, ' ').trim();

async function fillForm(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-test="new-fuel-log"]').trigger('click');
    await flushPromises();
    await wrapper.get('[data-test="vehicle_id"]').setValue('truck-1');
    await wrapper.get('[data-test="odometer_km"]').setValue(100_640);
    await wrapper.get('[data-test="liters"]').setValue('350,5');
    await wrapper.get('[data-test="total_cost"]').setValue('2.099,50');
    await wrapper.get('[data-test="station_name"]').setValue('Posto Graal Ourinhos');
    await wrapper.get('[data-test="fueled_at"]').setValue('2026-11-13T08:30');
}

describe('FuelView', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 10, 13, 10, 0));
        setActivePinia(createPinia());
        signInAs('fleet_manager');
        Object.values(http).forEach((mock) => mock.mockReset());
        monthFuel = { month_start: '2026-11-01T00:00:00+00:00', month_cost_cents: 2_150_590, month_liters_milliliters: 3_505_500, month_logs: 12 };
    });

    afterEach(() => vi.useRealTimers());

    it('lists each fuel log with plate, station, odometer, liters, cost in reais and efficiency', async () => {
        const wrapper = await mountView();
        const text = flat(rows(wrapper)[0]!.text());

        expect(http.get).toHaveBeenCalledWith('/fuel/logs', pageParams());
        expect(rows(wrapper)[0]!.get('[data-test="fueled-at"]').text()).toBe(formatDateTime('2026-11-12T11:00:00+00:00'));
        expect(rows(wrapper)[0]!.get('.plate').text()).toBe('ABC1D23');
        expect(text).toContain('Posto Graal Ourinhos');
        expect(text).toContain('por Fernanda Frota');
        expect(text).toContain('100.640 km');
        expect(rows(wrapper)[0]!.get('[data-test="distance"]').text()).toBe('+640 km');
        expect(rows(wrapper)[0]!.get('[data-test="row-liters"]').text()).toBe('200 L');
        expect(flat(rows(wrapper)[0]!.get('[data-test="row-cost"]').text())).toBe('R$ 1.198,00');
        expect(text).toContain('R$ 5,99/L');
        expect(rows(wrapper)[0]!.get('[data-test="efficiency"]').text()).toBe('3,20 km/L');
        expect(wrapper.get('[data-test="pagination-summary"]').text()).toBe('4 abastecimentos');
    });

    it('shows liters, costs and efficiencies in tabular figures', async () => {
        const row = rows(await mountView())[0]!;

        for (const hook of ['fueled-at', 'row-liters', 'row-cost', 'efficiency', 'distance']) {
            expect(row.get(`[data-test="${hook}"]`).classes()).toContain('num');
        }
    });

    it('flags only the fuel logs with consumption out of the standard', async () => {
        const wrapper = await mountView();

        expect(rows(wrapper)[0]!.find('[data-test="low-efficiency"]').exists()).toBe(false);
        expect(rows(wrapper)[1]!.get('[data-test="low-efficiency"]').text()).toBe('Consumo alto');
        expect(rows(wrapper)[1]!.get('[data-test="efficiency"]').text()).toBe('1,70 km/L');
        expect(rows(wrapper)[1]!.get('[data-test="efficiency"]').classes()).toContain('text-brake-700');
    });

    it('explains why a fuel log has no efficiency', async () => {
        const wrapper = await mountView();

        expect(rows(wrapper)[2]!.get('[data-test="no-efficiency"]').text()).toBe('Primeiro registro');
        expect(rows(wrapper)[2]!.find('[data-test="distance"]').exists()).toBe(false);
        expect(rows(wrapper)[2]!.get('[data-test="row-liters"]').text()).toBe('350,5 L');
        expect(rows(wrapper)[3]!.get('[data-test="no-efficiency"]').text()).toBe('Sem rodagem');
    });

    it('searches and pages on the server', async () => {
        const wrapper = await mountView();

        http.get.mockResolvedValue(page([thirsty], { total: 40, last_page: 3 }));
        await wrapper.get('[data-test="search"]').setValue('xyz');
        await wrapper.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fuel/logs', pageParams({ search: 'xyz' }));
        expect(rows(wrapper)).toHaveLength(1);

        await wrapper.get('[data-test="page-next"]').trigger('click');
        await flushPromises();

        expect(http.get).toHaveBeenLastCalledWith('/fuel/logs', pageParams({ page: 2, search: 'xyz' }));
    });

    it('records a fuel log from the side panel, sending milliliters and cents', async () => {
        const recorded = log('9', { km_per_liter: 3.2, liters_milliliters: 350_500 });
        http.post.mockResolvedValue({ data: { data: recorded } });
        const wrapper = await mountView([normal]);

        await fillForm(wrapper);

        expect(flat(wrapper.get('[data-test="odometer-hint"]').text())).toContain('No cadastro: 100.000 km');
        expect(flat(wrapper.get('[data-test="price-per-liter"]').text())).toBe('Preço por litro: R$ 5,99');

        logs = [recorded, normal];
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).toHaveBeenCalledWith('/fuel/logs', {
            vehicle_id: 'truck-1',
            odometer_km: 100_640,
            liters_milliliters: 350_500,
            total_cost_cents: 209_950,
            station_name: 'Posto Graal Ourinhos',
            fueled_at: new Date(2026, 10, 13, 8, 30).toISOString(),
        });
        expect(rows(wrapper)).toHaveLength(2);
        expect(lastToast()).toBe('Abastecimento registrado: 350,5 L no veículo ABC1D23, 3,20 km/L.');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('warns right away when the fuel log just recorded shows high consumption', async () => {
        http.post.mockResolvedValue({ data: { data: thirsty } });
        const wrapper = await mountView([normal]);

        await fillForm(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const toast = currentToasts().at(-1)!;

        expect(toast.tone).toBe('warning');
        expect(toast.title).toBe('Consumo alto');
        expect(toast.message).toContain('rendimento de 1,70 km/L');
        expect(toast.message).toContain('meta de 2,2 km/L deste veículo');
    });

    it('offers every vehicle, defaults the date to now and validates before calling the API', async () => {
        const wrapper = await mountView([normal]);

        await wrapper.get('[data-test="new-fuel-log"]').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('[data-test="vehicle_id"] option').slice(1).map((option) => option.text())).toEqual(['ABC1D23', 'XYZ9K88']);
        expect((wrapper.get('[data-test="fueled_at"]').element as HTMLInputElement).value).toBe('2026-11-13T10:00');

        await wrapper.get('[data-test="liters"]').setValue('350.5');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(http.post).not.toHaveBeenCalled();
        expect(wrapper.get('[data-test="vehicle_id-error"]').text()).toBe('Selecione o veículo.');
        expect(wrapper.get('[data-test="odometer_km-error"]').text()).toBe('Informe o hodômetro.');
        expect(wrapper.get('[data-test="liters-error"]').text()).toBe('Informe o volume em litros, como 350,5.');
        expect(wrapper.get('[data-test="total_cost-error"]').text()).toBe('Informe o valor total.');
        expect(wrapper.get('[data-test="station_name-error"]').text()).toBe('Informe o posto.');
        expect(wrapper.find('[data-test="price-per-liter"]').exists()).toBe(false);
    });

    it('shows on the right field why the API refused the fuel log', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: {
                status: 422,
                data: { errors: { odometer_km: ['O hodômetro não pode ser menor que o do último abastecimento deste veículo (100900 km).'], liters_milliliters: ['O campo volume é obrigatório.'], total_cost_cents: ['O campo valor total é obrigatório.'] } },
            },
        });
        const wrapper = await mountView([normal]);

        await fillForm(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="odometer_km-error"]').text()).toContain('100900 km');
        expect(wrapper.get('[data-test="liters-error"]').text()).toBe('O campo volume é obrigatório.');
        expect(wrapper.get('[data-test="total_cost-error"]').text()).toBe('O campo valor total é obrigatório.');
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('shows a general message on unexpected failures', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const wrapper = await mountView([normal]);

        await fillForm(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[data-test="error"]').text()).toContain('Não foi possível registrar o abastecimento');
    });

    it.each(['dispatcher', 'mechanic_supervisor'] as const)('is read-only for %s, who only has fleet.view', async (role) => {
        signInAs(role);
        const wrapper = await mountView();

        expect(rows(wrapper)).toHaveLength(4);
        expect(wrapper.find('[data-test="new-fuel-log"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="remove"]').exists()).toBe(false);
    });

    it('shows the empty, no-results and error states', async () => {
        const empty = await mountView([]);
        expect(empty.get('[data-test="empty"]').text()).toContain('Nenhum abastecimento registrado');

        await empty.get('[data-test="search"]').setValue('nada');
        await empty.get('[data-test="search"]').trigger('keydown.enter');
        await flushPromises();
        expect(empty.find('[data-test="no-results"]').exists()).toBe(true);

        setActivePinia(createPinia());
        signInAs('fleet_manager');
        http.get.mockRejectedValue(new Error('network'));
        const failed = mount(FuelView);
        await flushPromises();

        expect(failed.find('[data-test="list-error"]').exists()).toBe(true);
    });

    it('shows the month summary: spent, volume and number of fuel logs', async () => {
        const wrapper = await mountView();

        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(flat(wrapper.get('[data-test="summary-cost"]').text())).toContain('Gasto em novembro');
        expect(flat(wrapper.get('[data-test="summary-cost"]').text())).toContain('R$ 21.505,90');
        expect(flat(wrapper.get('[data-test="summary-liters"]').text())).toContain('3.505,5 L');
        expect(flat(wrapper.get('[data-test="summary-logs"]').text())).toContain('Abastecimentos em novembro');
        expect(wrapper.get('[data-test="summary-logs"] [data-test="stat-value"]').text()).toBe('12');
    });

    it('deletes a fuel log only after confirmation, then reloads the list and the summary', async () => {
        http.delete.mockResolvedValue({});
        const wrapper = await mountView([normal, thirsty]);

        await rows(wrapper)[1]!.get('[data-test="remove"]').trigger('click');

        expect(http.delete).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alertdialog"]').text()).toContain('Excluir o abastecimento de XYZ9K88?');

        logs = [normal];
        monthFuel = { ...monthFuel, month_cost_cents: 2_030_790, month_logs: 11 };
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(http.delete).toHaveBeenCalledWith('/fuel/logs/2');
        expect(rows(wrapper)).toHaveLength(1);
        expect(flat(wrapper.get('[data-test="summary-cost"]').text())).toContain('R$ 20.307,90');
        expect(wrapper.get('[data-test="summary-logs"] [data-test="stat-value"]').text()).toBe('11');
        expect(lastToast()).toBe(`Abastecimento de XYZ9K88 em ${formatDateTime(thirsty.fueled_at)} excluído.`);
        expect(wrapper.find('[data-test="confirm-ok"]').exists()).toBe(false);
    });

    it('keeps the fuel log when the deletion is canceled', async () => {
        const wrapper = await mountView([normal]);

        await rows(wrapper)[0]!.get('[data-test="remove"]').trigger('click');
        await wrapper.get('[data-test="confirm-cancel"]').trigger('click');

        expect(http.delete).not.toHaveBeenCalled();
        expect(rows(wrapper)).toHaveLength(1);
    });

    it('explains in the dialog when the deletion fails, and does not carry the error to the next one', async () => {
        http.delete.mockRejectedValue({ isAxiosError: true, response: { status: 404, data: { message: 'Abastecimento 1 não encontrado.' } } });
        const wrapper = await mountView([normal, thirsty]);

        await rows(wrapper)[0]!.get('[data-test="remove"]').trigger('click');
        await wrapper.get('[data-test="confirm-ok"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="confirm-error"]').text()).toBe('Abastecimento 1 não encontrado.');
        expect(rows(wrapper)).toHaveLength(2);

        await wrapper.get('[data-test="confirm-cancel"]').trigger('click');
        await rows(wrapper)[1]!.get('[data-test="remove"]').trigger('click');

        expect(wrapper.find('[data-test="confirm-error"]').exists()).toBe(false);
    });

    it('names the fuel log on the delete button for screen readers', async () => {
        const wrapper = await mountView([normal]);

        expect(rows(wrapper)[0]!.get('[data-test="remove"]').attributes('aria-label')).toBe(`Excluir abastecimento de ABC1D23 em ${formatDateTime(normal.fueled_at)}`);
    });
});
