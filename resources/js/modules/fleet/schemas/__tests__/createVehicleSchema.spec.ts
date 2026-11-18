import { describe, expect, it } from 'vitest';
import { createVehicleSchema } from '../createVehicleSchema';

const valid = {
    plate: 'abc-1d23',
    chassis: '9bwzzz377vt004251',
    vehicle_type: 'truck',
    model: '',
    payload_kg: 12000,
    current_mileage: 120000,
    min_km_per_liter: '',
};

describe('createVehicleSchema', () => {
    it('normalizes plate and chassis', () => {
        const result = createVehicleSchema.parse(valid);

        expect(result.plate).toBe('ABC1D23');
        expect(result.chassis).toBe('9BWZZZ377VT004251');
    });

    it('turns an empty model and target into null, so the vehicle follows the default of its type', () => {
        const result = createVehicleSchema.parse(valid);

        expect(result.model).toBeNull();
        expect(result.min_meters_per_liter).toBeNull();
        expect(result).not.toHaveProperty('min_km_per_liter');
    });

    it('converts the target from km/L to meters per liter', () => {
        expect(createVehicleSchema.parse({ ...valid, min_km_per_liter: 4.5 }).min_meters_per_liter).toBe(4500);
        expect(createVehicleSchema.parse({ ...valid, min_km_per_liter: 2.15 }).min_meters_per_liter).toBe(2150);
    });

    it.each([
        ['plate', { plate: 'AB12345' }],
        ['chassis', { chassis: '9BWZZZ377VT00425O' }],
        ['payload_kg', { payload_kg: 0 }],
        ['payload_kg', { payload_kg: null }],
        ['payload_kg', { payload_kg: 60_001 }],
        ['vehicle_type', { vehicle_type: '' }],
        ['vehicle_type', { vehicle_type: 'executivo' }],
        ['model', { model: 'x'.repeat(61) }],
        ['min_km_per_liter', { min_km_per_liter: 0.4 }],
        ['current_mileage', { current_mileage: -1 }],
    ])('rejects invalid %s', (field, override) => {
        const result = createVehicleSchema.safeParse({ ...valid, ...override });

        expect(result.success).toBe(false);
        expect(result.error?.issues[0]?.path).toEqual([field]);
    });
});
