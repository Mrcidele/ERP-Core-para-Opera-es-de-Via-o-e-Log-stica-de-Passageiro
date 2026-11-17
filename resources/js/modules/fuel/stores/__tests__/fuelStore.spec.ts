import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { useFuelStore } from '../fuelStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const payload = { vehicle_id: 'bus-1', odometer_km: 100_640, liters_milliliters: 350_500, total_cost_cents: 209_950, station_name: 'Posto Graal', fueled_at: '2026-11-13T11:30:00.000Z' };
const log = { id: 'fuel-1', ...payload, distance_km: 640, km_per_liter: 1.83, low_efficiency_alert: true, vehicle: { id: 'bus-1', plate: 'ABC1D23' }, recorded_by: { id: 'u1', name: 'Ana' } };

describe('fuelStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('loads the first page of fuel logs and searches on the server', async () => {
        http.get.mockResolvedValue(page([log], { total: 31, last_page: 3 }));
        const store = useFuelStore();

        await store.fetchLogs();
        await store.applyFilters({ search: 'graal' });

        expect(http.get).toHaveBeenNthCalledWith(1, '/fuel/logs', pageParams());
        expect(http.get).toHaveBeenNthCalledWith(2, '/fuel/logs', pageParams({ search: 'graal' }));
        expect(store.logs).toEqual([log]);
        expect(store.meta.total).toBe(31);
        expect(store.filtering).toBe(true);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useFuelStore();

        await store.fetchLogs();

        expect(store.listStatus).toBe('error');
    });

    it('offers every vehicle of the fleet, whatever its status', async () => {
        http.get.mockResolvedValue(page([{ id: 'bus-1', status: 'active' }, { id: 'bus-2', status: 'maintenance' }]));
        const store = useFuelStore();

        await store.loadOptions();

        expect(http.get).toHaveBeenCalledWith('/fleet/vehicles', { params: { page: 1, per_page: 100 } });
        expect(store.vehicles.map((vehicle) => vehicle.id)).toEqual(['bus-1', 'bus-2']);
        expect(store.optionsStatus).toBe('success');
    });

    it('flags when the vehicles cannot be loaded', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useFuelStore();

        await store.loadOptions();

        expect(store.optionsStatus).toBe('error');
    });

    it('records a fuel log and goes back to the first page, where it appears', async () => {
        http.post.mockResolvedValue({ data: { data: log } });
        http.get.mockResolvedValue(page([log]));
        const store = useFuelStore();
        await store.fetchLogs(3);

        const recorded = await store.record(payload);

        expect(http.post).toHaveBeenCalledWith('/fuel/logs', payload);
        expect(recorded).toEqual(log);
        expect(http.get).toHaveBeenCalledWith('/fuel/logs', pageParams());
        // O resumo do mês acompanha o registro novo.
        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(store.saveStatus).toBe('success');
    });

    it('keeps the field errors of the API and does not reload the list', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { odometer_km: ['O hodômetro não pode ser menor que o do último abastecimento deste veículo (100600 km).'] } } },
        });
        const store = useFuelStore();

        expect(await store.record(payload)).toBeNull();
        expect(store.validationErrors.odometer_km?.[0]).toContain('100600 km');
        expect(store.saveError).toBeNull();
        expect(http.get).not.toHaveBeenCalled();
    });

    it('uses a generic message for unexpected failures and clears it on reset', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const store = useFuelStore();

        await store.record(payload);
        expect(store.saveError).toContain('Não foi possível registrar o abastecimento');

        store.resetSaveState();
        expect(store.saveError).toBeNull();
    });

    it('loads the month summary from the dashboard', async () => {
        const fuel = { month_start: '2026-11-01T00:00:00+00:00', month_cost_cents: 2_150_590, month_liters_milliliters: 3_505_500, month_logs: 12 };
        http.get.mockResolvedValue({ data: { data: { fuel } } });
        const store = useFuelStore();

        await store.fetchSummary();

        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(store.monthSummary).toEqual(fuel);
        expect(store.summaryStatus).toBe('success');
    });

    it('deletes a fuel log and reloads the current page and the month summary', async () => {
        http.get.mockResolvedValue(page([log], { current_page: 2, total: 20, last_page: 2 }));
        http.delete.mockResolvedValue({});
        const store = useFuelStore();
        await store.fetchLogs(2);
        http.get.mockClear();
        http.get.mockImplementation((url: string) => Promise.resolve(url === '/dashboard/summary' ? { data: { data: { fuel: null } } } : page([], { current_page: 2, total: 19, last_page: 2 })));

        expect(await store.remove('fuel-1')).toBe(true);

        expect(http.delete).toHaveBeenCalledWith('/fuel/logs/fuel-1');
        expect(http.get).toHaveBeenCalledWith('/fuel/logs', pageParams({ page: 2 }));
        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(store.removeStatus).toBe('success');
    });

    it('keeps the list and explains when the deletion fails', async () => {
        http.delete.mockRejectedValue({ isAxiosError: true, response: { status: 404, data: { message: 'Abastecimento fuel-1 não encontrado.' } } });
        const store = useFuelStore();

        expect(await store.remove('fuel-1')).toBe(false);
        expect(store.removeError).toBe('Abastecimento fuel-1 não encontrado.');
        expect(http.get).not.toHaveBeenCalled();

        store.resetRemoveState();
        expect(store.removeError).toBeNull();
        expect(store.removeStatus).toBe('idle');
    });
});
