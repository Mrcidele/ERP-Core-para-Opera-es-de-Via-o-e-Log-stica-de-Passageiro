import { describe, expect, it } from 'vitest';
import { formatCents } from '@/core/utils/format';
import { completeMaintenanceOrderSchema, openMaintenanceOrderSchema, reaisToCents } from '../maintenanceOrderSchemas';

const valid = { vehicle_id: 'bus-1', mechanic_id: 'mechanic-1', type: 'corrective', description: '  Reparo no ar-condicionado ', estimated_completion_at: '' };

describe('openMaintenanceOrderSchema', () => {
    it('accepts a valid order and trims the description', () => {
        expect(openMaintenanceOrderSchema.parse(valid)).toEqual({ ...valid, description: 'Reparo no ar-condicionado', estimated_completion_at: null });
    });

    it('sends the estimated completion typed in local time as ISO 8601 UTC', () => {
        expect(openMaintenanceOrderSchema.parse({ ...valid, estimated_completion_at: '2099-11-14T18:00' }).estimated_completion_at).toBe(new Date(2099, 10, 14, 18, 0).toISOString());
    });

    it.each([
        ['2000-01-01T08:00', 'A previsão de término deve estar no futuro.'],
        ['14/11/2099 18:00', 'Informe a data e a hora da previsão.'],
    ])('rejects the estimated completion %s', (estimate, message) => {
        const result = openMaintenanceOrderSchema.safeParse({ ...valid, estimated_completion_at: estimate });

        expect(result.error?.issues.map((issue) => [issue.path[0], issue.message])).toEqual([['estimated_completion_at', message]]);
    });

    it.each([
        ['vehicle_id', { vehicle_id: '' }],
        ['mechanic_id', { mechanic_id: '' }],
        ['type', { type: '' }],
        ['type', { type: 'cosmetic' }],
        ['description', { description: 'óleo' }],
        ['description', { description: 'x'.repeat(2001) }],
    ])('rejects invalid %s', (field, override) => {
        const result = openMaintenanceOrderSchema.safeParse({ ...valid, ...override });

        expect(result.error?.issues.map((issue) => issue.path[0])).toEqual([field]);
    });
});

describe('completeMaintenanceOrderSchema', () => {
    it.each([
        ['0', 0],
        ['450', 45_000],
        ['450,9', 45_090],
        ['450,90', 45_090],
        ['1.234,56', 123_456],
        ['1234,56', 123_456],
        [' 0,07 ', 7],
        ['19,99', 1_999],
    ])('converts %s reais to cents', (cost, cents) => {
        expect(completeMaintenanceOrderSchema.parse({ cost })).toEqual({ status: 'completed', cost_cents: cents });
    });

    it.each(['', '-10', '10.5', '1,234', 'abc', '12,345', '1.23,00', '99999999999'])('rejects %s', (cost) => {
        expect(completeMaintenanceOrderSchema.safeParse({ cost }).success).toBe(false);
    });

    it('does not lose cents to floating point', () => {
        expect(reaisToCents('0,29')).toBe(29);
        expect(reaisToCents('1.005,10')).toBe(100_510);
    });
});

describe('formatCents', () => {
    it('formats cents as Brazilian reais', () => {
        expect(formatCents(45_090).replace(/\s/g, ' ')).toBe('R$ 450,90');
        expect(formatCents(123_456).replace(/\s/g, ' ')).toBe('R$ 1.234,56');
    });
});
