import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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
    vehicle: { id: 'bus-1', plate: 'ABC1D23' },
    driver: { id: 'driver-1', name: 'João da Silva' },
});

describe('tripStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
        http.post.mockReset();
    });

    it('loads the trips', async () => {
        http.get.mockResolvedValue({ data: { data: [trip('1', 'scheduled')] } });
        const store = useTripStore();

        await store.fetchTrips();

        expect(http.get).toHaveBeenCalledWith('/operations/trips');
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
    ] as const)('posts the %s transition and updates only that trip', async (action, status) => {
        http.get.mockResolvedValue({ data: { data: [trip('1', 'scheduled'), trip('2', 'scheduled')] } });
        http.post.mockResolvedValue({ data: { data: { id: '1', status } } });
        const store = useTripStore();
        await store.fetchTrips();

        const ok = await store.transition('1', action);

        expect(ok).toBe(true);
        expect(http.post).toHaveBeenCalledWith(`/operations/trips/1/${action}`);
        expect(store.trips.map((item) => item.status)).toEqual([status, 'scheduled']);
        expect(store.transitioningId).toBeNull();
    });

    it('keeps the status and exposes the reason when the API refuses a transition', async () => {
        http.get.mockResolvedValue({ data: { data: [trip('1', 'scheduled')] } });
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
});
