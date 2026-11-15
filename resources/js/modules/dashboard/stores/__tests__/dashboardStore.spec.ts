import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useDashboardStore } from '../dashboardStore';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const summary = { generated_at: '2026-11-13T10:00:00+00:00', fleet: null, operations: null, crew: null, maintenance: null };

describe('dashboardStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        http.get.mockReset();
    });

    it('loads the summary from the dashboard endpoint', async () => {
        http.get.mockResolvedValue({ data: { data: summary } });
        const store = useDashboardStore();

        expect(store.status).toBe('idle');
        await store.fetchSummary();

        expect(http.get).toHaveBeenCalledWith('/dashboard/summary');
        expect(store.status).toBe('success');
        expect(store.summary).toEqual(summary);
    });

    it('flags a failed load and keeps the last summary', async () => {
        http.get.mockResolvedValueOnce({ data: { data: summary } }).mockRejectedValueOnce(new Error('network'));
        const store = useDashboardStore();

        await store.fetchSummary();
        await store.fetchSummary();

        expect(store.status).toBe('error');
        expect(store.summary).toEqual(summary);
    });
});
