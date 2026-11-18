import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import type { TripSummary } from '../../types/trip';
import { useTripStore } from '../tripStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const trip = (id: string, status: TripSummary['status']): TripSummary => ({
    id,
    status,
    departure_time: '2026-11-20T11:00:00+00:00',
    arrival_time: '2026-11-20T17:30:00+00:00',
    line: { id: 'line-1', origin: 'Curitiba', destination: 'Ourinhos', distance_km: 400 },
    vehicle: { id: 'truck-1', plate: 'ABC1D23' },
    driver: { id: 'driver-1', name: 'João da Silva' },
});

describe('tripStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
        http.post.mockReset();
    });

    it('loads the trips', async () => {
        http.get.mockResolvedValue(page([trip('1', 'scheduled')]));
        const store = useTripStore();

        await store.fetchTrips();

        expect(http.get).toHaveBeenCalledWith('/operations/trips', pageParams());
        expect(store.listStatus).toBe('success');
        expect(store.trips).toHaveLength(1);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useTripStore();

        await store.fetchTrips();

        expect(store.listStatus).toBe('error');
    });

    it.each([
        ['start', 'in_progress'],
        ['complete', 'completed'],
        ['cancel', 'canceled'],
    ] as const)('posts the %s transition and reloads the page', async (action, status) => {
        http.get.mockResolvedValue(page([trip('1', 'scheduled'), trip('2', 'scheduled')]));
        http.post.mockResolvedValue({ data: { data: { id: '1', status } } });
        const store = useTripStore();
        await store.fetchTrips();

        http.get.mockResolvedValue(page([trip('1', status), trip('2', 'scheduled')]));
        const ok = await store.transition('1', action);

        expect(ok).toBe(true);
        expect(http.get).toHaveBeenCalledTimes(2);
        expect(http.post).toHaveBeenCalledWith(`/operations/trips/1/${action}`);
        expect(store.trips.map((item) => item.status)).toEqual([status, 'scheduled']);
        expect(store.transitioningId).toBeNull();
    });

    it('keeps the status and exposes the reason when the API refuses a transition', async () => {
        http.get.mockResolvedValue(page([trip('1', 'scheduled')]));
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { status: ['Só é possível concluir uma viagem em andamento.'] } } },
        });
        const store = useTripStore();
        await store.fetchTrips();

        const ok = await store.transition('1', 'complete');

        expect(ok).toBe(false);
        expect(store.trips[0]?.status).toBe('scheduled');
        expect(store.transitionError).toBe('Só é possível concluir uma viagem em andamento.');
    });

    it('uses a generic message for unexpected failures', async () => {
        http.post.mockRejectedValue(new Error('network'));
        const store = useTripStore();

        await store.transition('1', 'start');

        expect(store.transitionError).toContain('Não foi possível atualizar');
    });

    it('keeps the filters when changing page and sends them to the export', async () => {
        http.get.mockResolvedValue(page([trip('1', 'completed')], { total: 40, last_page: 3 }));
        const store = useTripStore();

        await store.applyFilters({ status: 'completed', search: ' londrina ' });
        await store.fetchTrips(2);

        expect(http.get).toHaveBeenNthCalledWith(1, '/operations/trips', pageParams({ status: 'completed', search: 'londrina' }));
        expect(http.get).toHaveBeenNthCalledWith(2, '/operations/trips', pageParams({ page: 2, status: 'completed', search: 'londrina' }));
        expect(store.meta.total).toBe(40);
        expect(store.filtering).toBe(true);
    });

    it('loads every page of active vehicles and employees for the form options', async () => {
        http.get.mockImplementation((url: string, config?: { params?: { page?: number } }) => {
            if (url === '/operations/lines') {
                return Promise.resolve({ data: { data: [{ id: 'line-1' }] } });
            }

            const current = config?.params?.page ?? 1;
            const item = url === '/fleet/vehicles' ? { id: `truck-${current}`, status: 'active' } : { id: `emp-${current}`, status: 'active', role: current === 1 ? 'driver' : 'mechanic' };

            return Promise.resolve(page([item], { current_page: current, last_page: 2, per_page: 100, total: 2 }));
        });
        const store = useTripStore();

        await store.loadOptions();

        expect(http.get).toHaveBeenCalledWith('/fleet/vehicles', { params: { status: 'active', page: 2, per_page: 100 } });
        expect(store.vehicles.map((vehicle) => vehicle.id)).toEqual(['truck-1', 'truck-2']);
        expect(store.drivers.map((driver) => driver.id)).toEqual(['emp-1']);
        expect(store.optionsStatus).toBe('success');
    });
});
