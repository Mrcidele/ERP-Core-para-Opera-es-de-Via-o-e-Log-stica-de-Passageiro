import { describe, expect, it } from 'vitest';
import { fromDateInput, toDateInput, toDateTimeInput } from '../datetime';
import { formatKmPerLiter, formatLiters } from '../format';
import { LITERS, litersToMilliliters, REAIS, reaisToCents } from '../money';

describe('money and volume', () => {
    it.each([
        ['450', 45_000],
        ['450,9', 45_090],
        ['1.234,56', 123_456],
        ['0,29', 29],
    ])('converts %s reais to cents without floating point', (text, cents) => {
        expect(REAIS.test(text)).toBe(true);
        expect(reaisToCents(text)).toBe(cents);
    });

    it.each([
        ['350', 350_000],
        ['350,5', 350_500],
        ['1.250,125', 1_250_125],
        ['0,001', 1],
        ['0,29', 290],
    ])('converts %s liters to milliliters without floating point', (text, milliliters) => {
        expect(LITERS.test(text)).toBe(true);
        expect(litersToMilliliters(text)).toBe(milliliters);
    });

    it.each(['350.5', '1,2345', 'abc', '', '-3', '1.25,0'])('rejects %s as a volume', (text) => {
        expect(LITERS.test(text)).toBe(false);
    });

    it('formats liters and km per liter the Brazilian way', () => {
        expect(formatLiters(350_500)).toBe('350,5 L');
        expect(formatLiters(1_250_125)).toBe('1.250,125 L');
        expect(formatLiters(200_000)).toBe('200 L');
        expect(formatKmPerLiter(2.85)).toBe('2,85 km/L');
        expect(formatKmPerLiter(3)).toBe('3,00 km/L');
    });
});

describe('date inputs', () => {
    it('writes and reads local dates in the format of the native inputs', () => {
        const moment = new Date(2026, 10, 5, 8, 7);

        expect(toDateInput(moment)).toBe('2026-11-05');
        expect(toDateTimeInput(moment)).toBe('2026-11-05T08:07');
        expect(fromDateInput('2026-11-05').getTime()).toBe(new Date(2026, 10, 5).getTime());
    });
});
