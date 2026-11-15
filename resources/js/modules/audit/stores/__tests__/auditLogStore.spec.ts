import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { auditActionLabel, auditEntityLabel, isSensitiveAction } from '../../types/auditLog';
import { useAuditLogStore } from '../auditLogStore';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

describe('auditLogStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
    });

    it('loads the most recent records, asking for the maximum the API allows', async () => {
        const logs = [{ id: 'log-1', action: 'vehicle.deleted' }];
        http.get.mockResolvedValue({ data: { data: logs } });
        const store = useAuditLogStore();

        await store.fetchLogs();

        expect(http.get).toHaveBeenCalledWith('/audit-logs', { params: { limit: 200 } });
        expect(store.status).toBe('success');
        expect(store.logs).toEqual(logs);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useAuditLogStore();

        await store.fetchLogs();

        expect(store.status).toBe('error');
        expect(store.logs).toEqual([]);
    });
});

describe('audit labels', () => {
    it.each([
        ['vehicle.deleted', 'Veículo excluído'],
        ['trip.completed', 'Viagem concluída'],
        ['trip.canceled', 'Viagem cancelada'],
        ['maintenance_order.opened', 'Ordem de serviço aberta'],
        ['maintenance_order.completed', 'Ordem de serviço concluída'],
        ['auth.login_failed', 'Login recusado'],
        ['something.new', 'something.new'],
    ])('labels the action %s as "%s"', (action, label) => {
        expect(auditActionLabel(action)).toBe(label);
    });

    it('labels the entities and falls back to the code', () => {
        expect(['vehicle', 'trip', 'maintenance_order', 'user', 'other'].map(auditEntityLabel)).toEqual(['Veículo', 'Viagem', 'Ordem de serviço', 'Usuário', 'other']);
    });

    it('treats deletions and refused logins as sensitive', () => {
        expect(['vehicle.deleted', 'auth.login_failed', 'trip.completed', 'maintenance_order.opened'].map(isSensitiveAction)).toEqual([true, true, false, false]);
    });
});
