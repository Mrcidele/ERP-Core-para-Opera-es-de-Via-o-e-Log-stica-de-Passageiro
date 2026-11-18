import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { useMaintenanceStore } from '../maintenanceStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const order = { id: 'order-1', vehicle_id: 'truck-1', mechanic_id: 'mechanic-1', type: 'corrective', status: 'open', description: 'Reparo', cost_cents: 0, opened_at: '2026-11-13T10:00:00+00:00', completed_at: null, estimated_completion_at: null };
const summary = { ...order, vehicle: { id: 'truck-1', plate: 'ABC1D23' }, mechanic: { id: 'mechanic-1', name: 'Maria Souza' } };

describe('maintenanceStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
        http.get.mockResolvedValue(page([summary]));
    });

    it('loads the orders', async () => {
        const store = useMaintenanceStore();

        await store.fetchOrders();

        expect(http.get).toHaveBeenCalledWith('/maintenance/orders', pageParams());
        expect(store.listStatus).toBe('success');
        expect(store.orders).toEqual([summary]);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useMaintenanceStore();

        await store.fetchOrders();

        expect(store.listStatus).toBe('error');
    });

    it('offers every vehicle and the mechanics listed by the maintenance module', async () => {
        http.get.mockImplementation((url: string) =>
            Promise.resolve(
                url === '/fleet/vehicles'
                    ? page([{ id: 'truck-1', status: 'active' }, { id: 'truck-2', status: 'maintenance' }])
                    : { data: { data: [{ id: 'm1', name: 'Maria Souza' }] } },
            ),
        );
        const store = useMaintenanceStore();

        await store.loadOptions();

        expect(http.get.mock.calls.map(([url]) => url).sort()).toEqual(['/fleet/vehicles', '/maintenance/mechanics']);
        expect(store.vehicles.map((vehicle) => vehicle.id)).toEqual(['truck-1', 'truck-2']);
        expect(store.mechanics).toEqual([{ id: 'm1', name: 'Maria Souza' }]);
    });

    it('opens an order and reloads the list', async () => {
        http.post.mockResolvedValue({ data: { data: order } });
        http.get.mockResolvedValue(page([summary]));
        const store = useMaintenanceStore();
        const payload = { vehicle_id: 'truck-1', mechanic_id: 'mechanic-1', type: 'corrective' as const, description: 'Reparo', estimated_completion_at: '2026-11-14T21:00:00.000Z' };

        const result = await store.open(payload);

        expect(result).toEqual(order);
        expect(http.post).toHaveBeenCalledWith('/maintenance/orders', payload);
        expect(http.get).toHaveBeenCalledWith('/maintenance/orders', pageParams());
        expect(store.orders).toEqual([summary]);
        expect(store.saveStatus).toBe('success');
    });

    it('completes an order with its cost in cents', async () => {
        http.patch.mockResolvedValue({ data: { data: { ...order, status: 'completed', cost_cents: 45_090 } } });
        const store = useMaintenanceStore();

        const result = await store.complete('order-1', { status: 'completed', cost_cents: 45_090 });

        expect(result?.status).toBe('completed');
        expect(http.patch).toHaveBeenCalledWith('/maintenance/orders/order-1', { status: 'completed', cost_cents: 45_090 });
    });

    it('cancels an order', async () => {
        http.patch.mockResolvedValue({ data: { data: { ...order, status: 'canceled' } } });
        const store = useMaintenanceStore();

        await store.cancel('order-1');

        expect(http.patch).toHaveBeenCalledWith('/maintenance/orders/order-1', { status: 'canceled' });
    });

    it('keeps field errors from the API and does not reload the list', async () => {
        http.post.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { mechanic_id: ['O funcionário informado não existe, não é mecânico ou não está ativo.'] } } },
        });
        const store = useMaintenanceStore();

        const result = await store.open({ vehicle_id: 'truck-1', mechanic_id: 'driver-1', type: 'corrective', description: 'Reparo', estimated_completion_at: null });

        expect(result).toBeNull();
        expect(store.saveStatus).toBe('error');
        expect(store.validationErrors.mechanic_id?.[0]).toContain('não é mecânico');
        expect(store.saveError).toBeNull();
        expect(http.get).not.toHaveBeenCalled();
    });

    it('exposes a refused status change as a general error', async () => {
        http.patch.mockRejectedValue({
            isAxiosError: true,
            response: { status: 422, data: { errors: { status: ['Só é possível cancelar uma ordem aberta ou em andamento.'] } } },
        });
        const store = useMaintenanceStore();

        await store.cancel('order-1');

        expect(store.saveError).toBe('Só é possível cancelar uma ordem aberta ou em andamento.');
    });

    it('uses a generic message for unexpected failures', async () => {
        http.patch.mockRejectedValue(new Error('network'));
        const store = useMaintenanceStore();

        await store.complete('order-1', { status: 'completed', cost_cents: 0 });

        expect(store.saveError).toContain('Não foi possível concluir');
    });
});
