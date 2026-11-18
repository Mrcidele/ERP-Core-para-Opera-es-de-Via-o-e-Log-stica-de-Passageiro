import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Line } from '../../types/trip';
import { useLineStore } from '../lineStore';

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const line = (id: string, destination: string): Line => ({ id, origin: 'Curitiba', destination, distance_km: 400, estimated_duration_minutes: 390, stops: [] });
const payload = { origin: 'Curitiba', destination: 'Londrina', distance_km: 380, estimated_duration_minutes: 360, stops: [] };

const refusal = (errors: Record<string, string[]>) => ({ isAxiosError: true, response: { status: 422, data: { errors } } });

describe('lineStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        Object.values(http).forEach((mock) => mock.mockReset());
    });

    it('loads every line', async () => {
        http.get.mockResolvedValue({ data: { data: [line('1', 'Ourinhos')] } });
        const store = useLineStore();

        await store.fetchAll();

        expect(http.get).toHaveBeenCalledWith('/operations/lines');
        expect(store.listStatus).toBe('success');
        expect(store.lines).toHaveLength(1);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useLineStore();

        await store.fetchAll();

        expect(store.listStatus).toBe('error');
    });

    it('creates a line and keeps the list ordered by route', async () => {
        http.get.mockResolvedValue({ data: { data: [line('1', 'Foz do Iguaçu'), line('2', 'Ourinhos')] } });
        http.post.mockResolvedValue({ data: { data: line('3', 'Londrina') } });
        const store = useLineStore();
        await store.fetchAll();

        const created = await store.create(payload);

        expect(http.post).toHaveBeenCalledWith('/operations/lines', payload);
        expect(created?.id).toBe('3');
        expect(store.lines.map((item) => item.destination)).toEqual(['Foz do Iguaçu', 'Londrina', 'Ourinhos']);
        expect(store.saveStatus).toBe('success');
    });

    it('updates a line in place', async () => {
        http.get.mockResolvedValue({ data: { data: [line('1', 'Ourinhos')] } });
        http.put.mockResolvedValue({ data: { data: line('1', 'Londrina') } });
        const store = useLineStore();
        await store.fetchAll();

        await store.update('1', payload);

        expect(http.put).toHaveBeenCalledWith('/operations/lines/1', payload);
        expect(store.lines.map((item) => item.destination)).toEqual(['Londrina']);
    });

    it('keeps the field errors the API returned', async () => {
        http.post.mockRejectedValue(refusal({ stops: ['A parada 2 (Castro) deve ficar depois da parada anterior.'] }));
        const store = useLineStore();

        expect(await store.create(payload)).toBeNull();
        expect(store.validationErrors.stops?.[0]).toContain('parada 2');
        expect(store.saveError).toBeNull();
        expect(store.saveStatus).toBe('error');
    });

    it('uses a generic message for unexpected failures and clears it on reset', async () => {
        http.put.mockRejectedValue(new Error('network'));
        const store = useLineStore();

        await store.update('1', payload);
        expect(store.saveError).toContain('Não foi possível salvar');

        store.resetSaveState();
        expect(store.saveError).toBeNull();
        expect(store.saveStatus).toBe('idle');
    });

    it('removes a line from the list', async () => {
        http.get.mockResolvedValue({ data: { data: [line('1', 'Ourinhos'), line('2', 'Londrina')] } });
        http.delete.mockResolvedValue({});
        const store = useLineStore();
        await store.fetchAll();

        expect(await store.remove('1')).toBe(true);
        expect(http.delete).toHaveBeenCalledWith('/operations/lines/1');
        expect(store.lines.map((item) => item.id)).toEqual(['2']);
    });

    it('keeps the line and explains when it still has active trips', async () => {
        http.get.mockResolvedValue({ data: { data: [line('1', 'Ourinhos')] } });
        http.delete.mockRejectedValue(refusal({ line: ['Esta rota tem viagens agendadas ou em andamento.'] }));
        const store = useLineStore();
        await store.fetchAll();

        expect(await store.remove('1')).toBe(false);
        expect(store.lines).toHaveLength(1);
        expect(store.removeError).toBe('Esta rota tem viagens agendadas ou em andamento.');

        store.resetRemoveState();
        expect(store.removeError).toBeNull();
    });

    it('uses a generic message when the deletion fails for another reason', async () => {
        http.delete.mockRejectedValue(new Error('network'));
        const store = useLineStore();

        await store.remove('1');

        expect(store.removeError).toContain('Não foi possível excluir a rota');
    });
});
