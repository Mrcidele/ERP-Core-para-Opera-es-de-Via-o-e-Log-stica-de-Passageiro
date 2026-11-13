import { describe, expect, it } from 'vitest';
import { createVehicleSchema } from '../createVehicleSchema';

const valid = {
    plate: 'abc-1d23',
    chassis: '9bwzzz377vt004251',
    capacity: 46,
    class_type: 'executivo',
    current_mileage: 120000,
};

describe('createVehicleSchema', () => {
    it('normalizes plate and chassis', () => {
        const result = createVehicleSchema.parse(valid);

        expect(result.plate).toBe('ABC1D23');
        expect(result.chassis).toBe('9BWZZZ377VT004251');
    });

    it.each([
        ['plate', { plate: 'AB12345' }],
        ['chassis', { chassis: '9BWZZZ377VT00425O' }],
        ['capacity', { capacity: 0 }],
        ['capacity', { capacity: null }],
        ['class_type', { class_type: '' }],
        ['current_mileage', { current_mileage: -1 }],
    ])('rejects invalid %s', (field, override) => {
        const result = createVehicleSchema.safeParse({ ...valid, ...override });

        expect(result.success).toBe(false);
        expect(result.error?.issues[0]?.path).toEqual([field]);
    });
});
