import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTimelineStore } from '../timelineStore';

const http = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@/core/api/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/core/api/http')>()),
    http,
}));

const timeline = { from: '2026-11-20T00:00:00+00:00', to: '2026-11-22T00:00:00+00:00', driver_rest_hours: 11, vehicles: [], drivers: [] };

/** Data UTC em que cai a meia-noite local de um dia: é o que a tela pede à API. */
const utcDateOfLocalMidnight = (year: number, month: number, day: number): string => new Date(year, month - 1, day).toISOString().slice(0, 10);

describe('timelineStore', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 10, 20, 15, 30));
        setActivePinia(createPinia());
        http.get.mockReset();
        http.get.mockResolvedValue({ data: { data: timeline } });
    });

    afterEach(() => vi.useRealTimers());

    it('starts on today and asks for the window that covers the local day', async () => {
        const store = useTimelineStore();

        await store.fetch();

        expect(store.date).toBe('2026-11-20');
        expect(store.start.getTime()).toBe(new Date(2026, 10, 20).getTime());
        expect(http.get).toHaveBeenCalledWith('/operations/schedule-timeline', { params: { date: utcDateOfLocalMidnight(2026, 11, 20) } });
        expect(store.timeline).toEqual(timeline);
        expect(store.status).toBe('success');
    });

    it('always asks for a window that contains the local midnight of the chosen day', async () => {
        const store = useTimelineStore();

        await store.showDate('2026-12-31');

        const from = new Date(`${store.requestDate}T00:00:00Z`).getTime();
        const localMidnight = new Date(2026, 11, 31).getTime();

        expect(localMidnight).toBeGreaterThanOrEqual(from);
        expect(localMidnight - from).toBeLessThan(24 * 3_600_000);
    });

    it('moves one day back and forth, across months, and returns to today', async () => {
        const store = useTimelineStore();

        await store.showDate('2026-11-30');
        await store.shiftDays(1);
        expect(store.date).toBe('2026-12-01');

        await store.shiftDays(-2);
        expect(store.date).toBe('2026-11-29');
        expect(http.get).toHaveBeenLastCalledWith('/operations/schedule-timeline', { params: { date: utcDateOfLocalMidnight(2026, 11, 29) } });

        await store.showToday();
        expect(store.date).toBe('2026-11-20');
    });

    it('keeps the day when the date typed is not valid', async () => {
        const store = useTimelineStore();

        await store.showDate('');

        expect(store.date).toBe('2026-11-20');
    });

    it('keeps the grid on screen while another day loads', async () => {
        const store = useTimelineStore();
        await store.fetch();

        let release: (value: unknown) => void = () => undefined;
        http.get.mockReturnValue(new Promise((resolve) => (release = resolve)));
        const pending = store.shiftDays(1);

        expect(store.status).toBe('success');
        expect(store.refreshing).toBe(true);

        release({ data: { data: { ...timeline, driver_rest_hours: 12 } } });
        await pending;

        expect(store.refreshing).toBe(false);
        expect(store.timeline?.driver_rest_hours).toBe(12);
    });

    it('flags a failed load', async () => {
        http.get.mockRejectedValue(new Error('network'));
        const store = useTimelineStore();

        await store.fetch();

        expect(store.status).toBe('error');
        expect(store.timeline).toBeNull();
    });
});
