import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { isValidCpf, maskCpf } from '@/core/utils/cpf';
import { createEmployeeSchema } from '../createEmployeeSchema';

const driver = {
    name: '  João da Silva ',
    cpf: '529.982.247-25',
    role: 'driver',
    status: 'active',
    cnh_number: '12345678901',
    cnh_category: 'd',
    cnh_expiration_date: '2027-06-30',
};

const noCnh = { cnh_number: '', cnh_category: '', cnh_expiration_date: '' };

const firstErrorField = (input: unknown): unknown => createEmployeeSchema.safeParse(input).error?.issues[0]?.path[0];

describe('cpf utils', () => {
    it('masks progressively', () => {
        expect(maskCpf('529')).toBe('529');
        expect(maskCpf('5299')).toBe('529.9');
        expect(maskCpf('5299822')).toBe('529.982.2');
        expect(maskCpf('52998224725999')).toBe('529.982.247-25');
    });

    it('validates check digits', () => {
        expect(isValidCpf('529.982.247-25')).toBe(true);
        expect(isValidCpf('11144477735')).toBe(true);
        expect(isValidCpf('529.982.247-26')).toBe(false);
        expect(isValidCpf('111.111.111-11')).toBe(false);
        expect(isValidCpf('123')).toBe(false);
    });
});

describe('createEmployeeSchema', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 13, 12));
    });

    afterEach(() => vi.useRealTimers());

    it('normalizes a valid driver', () => {
        expect(createEmployeeSchema.parse(driver)).toEqual({
            name: 'João da Silva',
            cpf: '52998224725',
            role: 'driver',
            status: 'active',
            cnh_number: '12345678901',
            cnh_category: 'D',
            cnh_expiration_date: '2027-06-30',
        });
    });

    it('accepts a mechanic without license and sends nulls', () => {
        expect(createEmployeeSchema.parse({ ...driver, role: 'mechanic', ...noCnh })).toMatchObject({
            role: 'mechanic',
            cnh_number: null,
            cnh_category: null,
            cnh_expiration_date: null,
        });
    });

    it('requires the three license fields for a driver', () => {
        const result = createEmployeeSchema.safeParse({ ...driver, ...noCnh });

        expect(result.error?.issues.map((issue) => issue.path[0])).toEqual(['cnh_number', 'cnh_category', 'cnh_expiration_date']);
    });

    it.each([
        ['name', { name: 'Jo' }],
        ['cpf', { cpf: '529.982.247-26' }],
        ['role', { role: '' }],
        ['status', { status: 'fired' }],
        ['cnh_number', { cnh_number: '123' }],
        ['cnh_category', { cnh_category: 'Z' }],
        ['cnh_expiration_date', { cnh_expiration_date: '2026-11-13' }],
        ['cnh_expiration_date', { cnh_expiration_date: '2026-11-12' }],
        ['cnh_expiration_date', { cnh_expiration_date: '2027-02-30' }],
    ])('rejects invalid %s', (field, override) => {
        expect(firstErrorField({ ...driver, ...override })).toBe(field);
    });

    it('accepts a license that expires tomorrow', () => {
        expect(createEmployeeSchema.safeParse({ ...driver, cnh_expiration_date: '2026-11-14' }).success).toBe(true);
    });
});
