import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { page, pageParams } from '@/tests/paging';
import { auditEntityLabel, isSensitiveAction } from '../../types/auditLog';
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

    it('loads the first page of the trail', async () => {
        const logs = [{ id: 'log-1', action: 'vehicle.deleted' }];
        http.get.mockResolvedValue(page(logs));
        const store = useAuditLogStore();

        await store.fetchLogs();

        expect(http.get).toHaveBeenCalledWith('/audit-logs', pageParams());
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
    it('labels the entities and falls back to the code', () => {
        expect(['vehicle', 'line', 'trip', 'maintenance_order', 'user', 'other'].map(auditEntityLabel)).toEqual(['Veículo', 'Linha', 'Viagem', 'Ordem de serviço', 'Usuário', 'other']);
    });

    it('treats deletions and refused logins as sensitive', () => {
        expect(['vehicle.deleted', 'line.deleted', 'auth.login_failed', 'trip.completed', 'maintenance_order.opened'].map(isSensitiveAction)).toEqual([true, true, true, false, false]);
    });
});
