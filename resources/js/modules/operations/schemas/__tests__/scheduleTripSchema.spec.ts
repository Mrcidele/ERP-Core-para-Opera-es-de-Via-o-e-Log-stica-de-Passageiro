import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { formatDuration } from '@/core/utils/format';
import { scheduleTripSchema } from '../scheduleTripSchema';

const valid = {
    line_id: 'line-1',
    vehicle_id: 'bus-1',
    driver_id: 'driver-1',
    departure_time: '2026-11-20T08:00',
    arrival_time: '2026-11-20T14:30',
};

const errorFields = (input: unknown): unknown[] =>
    scheduleTripSchema.safeParse(input).error?.issues.map((issue) => issue.path[0]) ?? [];

describe('scheduleTripSchema', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 13, 10, 0));
    });

    afterEach(() => vi.useRealTimers());

    it('converts local date-times to ISO 8601 UTC', () => {
        const result = scheduleTripSchema.parse(valid);

        expect(result.departure_time).toBe(new Date(2026, 10, 20, 8, 0).toISOString());
        expect(result.arrival_time).toBe(new Date(2026, 10, 20, 14, 30).toISOString());
        expect(result.departure_time).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
    });

    it('requires line, vehicle, driver and both date-times', () => {
        expect(errorFields({ line_id: '', vehicle_id: '', driver_id: '', departure_time: '', arrival_time: '' })).toEqual([
            'line_id',
            'vehicle_id',
            'driver_id',
            'departure_time',
            'arrival_time',
        ]);
    });

    it.each([
        ['departure in the past', { departure_time: '2026-11-13T09:59' }, 'departure_time'],
        ['departure right now', { departure_time: '2026-11-13T10:00' }, 'departure_time'],
        ['arrival before departure', { arrival_time: '2026-11-20T07:59' }, 'arrival_time'],
        ['arrival equal to departure', { arrival_time: '2026-11-20T08:00' }, 'arrival_time'],
        ['malformed arrival', { arrival_time: '20/11/2026 14:30' }, 'arrival_time'],
    ])('rejects %s', (_label, override, field) => {
        expect(errorFields({ ...valid, ...override })).toEqual([field]);
    });

    it('accepts an arrival on the following day', () => {
        expect(scheduleTripSchema.safeParse({ ...valid, departure_time: '2026-11-20T22:00', arrival_time: '2026-11-21T04:30' }).success).toBe(true);
    });
});

describe('formatDuration', () => {
    it('formats minutes as hours and minutes', () => {
        expect(formatDuration(390)).toBe('6h 30min');
        expect(formatDuration(120)).toBe('2h');
        expect(formatDuration(45)).toBe('45min');
    });
});
