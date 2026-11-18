import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fuelLogSchema } from '../fuelLogSchema';

const valid = {
    vehicle_id: 'truck-1',
    odometer_km: 100_640,
    liters: '350,5',
    total_cost: '2.099,50',
    station_name: '  Posto Graal ',
    fueled_at: '2026-11-13T08:30',
};

function issues(input: unknown): Record<string, string> {
    const result = fuelLogSchema.safeParse(input);

    const messages: Record<string, string> = {};

    // Como o formulário: vale a primeira mensagem de cada campo.
    for (const issue of result.success ? [] : result.error.issues) {
        messages[String(issue.path[0])] ??= issue.message;
    }

    return messages;
}

describe('fuelLogSchema', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date(2026, 10, 13, 10, 0));
    });

    afterEach(() => vi.useRealTimers());

    it('sends milliliters, cents and the date in ISO 8601 UTC', () => {
        expect(fuelLogSchema.parse(valid)).toEqual({
            vehicle_id: 'truck-1',
            odometer_km: 100_640,
            liters_milliliters: 350_500,
            total_cost_cents: 209_950,
            station_name: 'Posto Graal',
            fueled_at: new Date(2026, 10, 13, 8, 30).toISOString(),
        });
    });

    it('accepts a fuel log made right now and one that cost nothing', () => {
        expect(fuelLogSchema.safeParse({ ...valid, fueled_at: '2026-11-13T10:00', total_cost: '0' }).success).toBe(true);
    });

    it.each([
        [{ vehicle_id: '' }, 'vehicle_id', 'Selecione o veículo.'],
        [{ odometer_km: undefined }, 'odometer_km', 'Informe o hodômetro.'],
        [{ odometer_km: -1 }, 'odometer_km', 'O hodômetro não pode ser negativo.'],
        [{ odometer_km: 10.5 }, 'odometer_km', 'O hodômetro deve ser um número inteiro de km.'],
        [{ liters: '' }, 'liters', 'Informe o volume abastecido.'],
        [{ liters: '350.5' }, 'liters', 'Informe o volume em litros, como 350,5.'],
        [{ liters: '0' }, 'liters', 'O volume deve ser maior que zero.'],
        [{ liters: '5.000,001' }, 'liters', 'O volume passa de 5.000 litros.'],
        [{ total_cost: '' }, 'total_cost', 'Informe o valor total.'],
        [{ total_cost: 'R$ 10' }, 'total_cost', 'Informe o valor em reais, como 2.099,50.'],
        [{ total_cost: '10.000.000,01' }, 'total_cost', 'O valor é alto demais.'],
        [{ station_name: '  ' }, 'station_name', 'Informe o posto.'],
        [{ fueled_at: '' }, 'fueled_at', 'Informe a data e a hora do abastecimento.'],
        [{ fueled_at: '2026-11-13T10:01' }, 'fueled_at', 'A data do abastecimento não pode estar no futuro.'],
    ])('rejects %o', (overrides, field, message) => {
        expect(issues({ ...valid, ...overrides })[field]).toBe(message);
    });
});
