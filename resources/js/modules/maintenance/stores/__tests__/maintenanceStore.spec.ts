import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useMaintenanceStore } from '../maintenanceStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const order = { id: 'order-1', vehicle_id: 'bus-1', mechanic_id: 'mechanic-1', type: 'corrective', status: 'open', description: 'Reparo', cost_cents: 0, opened_at: '2026-11-13T10:00:00+00:00', completed_at: null };
const summary = { ...order, vehicle: { id: 'bus-1', plate: 'ABC1D23' }, mechanic: { id: 'mechanic-1', name: 'Maria Souza' } };

const employee = (id: string, role: string, status: string) => ({ id, name: id, cpf: '52998224725', role, status, cnh_number: null, cnh_category: null, cnh_expiration_date: null });

describe('maintenanceStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
        http.get.mockResolvedValue({ data: { data: [summary] } });
    });

    it('loads the orders', async () => {
        const store = useMaintenanceStore();

        await store.fetchOrders();

        expect(http.get).toHaveBeenCalledWith('/maintenance/orders');
        expect(store.listStatus).toBe('success');
        expect(store.orders).toEqual([summary]);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useMaintenanceStore();

        await store.fetchOrders();

        expect(store.listStatus).toBe('error');
    });

    it('offers every vehicle and only active mechanics as options', async () => {
        http.get.mockImplementation((url: string) =>
            Promise.resolve({
                data: {
                    data:
                        url === '/vehicles'
                            ? [{ id: 'bus-1', status: 'active' }, { id: 'bus-2', status: 'maintenance' }]
                            : [employee('m1', 'mechanic', 'active'), employee('m2', 'mechanic', 'inactive'), employee('d1', 'driver', 'active')],
                },
            }),
        );
        const store = useMaintenanceStore();

        await store.loadOptions();

        expect(store.vehicles.map((vehicle) => vehicle.id)).toEqual(['bus-1', 'bus-2']);
        expect(store.mechanics.map((mechanic) => mechanic.id)).toEqual(['m1']);
    });

    it('opens an order and reloads the list', async () => {
        http.post.mockResolvedValue({ data: { data: order } });
        const store = useMaintenanceStore();
        const payload = { vehicle_id: 'bus-1', mechanic_id: 'mechanic-1', type: 'corrective' as const, description: 'Reparo' };

        const result = await store.open(payload);

        expect(result).toEqual(order);
        expect(http.post).toHaveBeenCalledWith('/maintenance/orders', payload);
        expect(http.get).toHaveBeenCalledWith('/maintenance/orders');
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

        const result = await store.open({ vehicle_id: 'bus-1', mechanic_id: 'driver-1', type: 'corrective', description: 'Reparo' });

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
