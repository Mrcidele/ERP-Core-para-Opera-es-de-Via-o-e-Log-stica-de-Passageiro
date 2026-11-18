import { describe, expect, it } from 'vitest';
import { lineSchema, MAX_LINE_STOPS } from '../lineSchema';

const stop = (city_name: string, cumulative_km: number, estimated_minutes: number) => ({ city_name, cumulative_km, estimated_minutes });

const valid = {
    origin: ' Curitiba ',
    destination: 'Ourinhos',
    distance_km: 400,
    estimated_duration_minutes: 390,
    stops: [stop(' Ponta Grossa ', 115, 105), stop('Castro', 160, 150)],
};

/** Mensagens por caminho de campo, como o formulário exibe. */
function issues(input: unknown): Record<string, string> {
    const result = lineSchema.safeParse(input);

    return result.success ? {} : Object.fromEntries(result.error.issues.map((issue) => [issue.path.join('.'), issue.message]));
}

describe('lineSchema', () => {
    it('accepts a line with ordered stops and trims the city names', () => {
        const result = lineSchema.parse(valid);

        expect(result.origin).toBe('Curitiba');
        expect(result.stops.map((item) => item.city_name)).toEqual(['Ponta Grossa', 'Castro']);
    });

    it('accepts a line without stops', () => {
        expect(lineSchema.safeParse({ ...valid, stops: [] }).success).toBe(true);
    });

    it.each([
        [{ origin: ' ' }, 'origin', 'Informe a cidade de origem.'],
        [{ destination: '' }, 'destination', 'Informe a cidade de destino.'],
        [{ destination: 'CURITIBA' }, 'destination', 'O destino deve ser diferente da origem.'],
        [{ distance_km: undefined }, 'distance_km', 'Informe a distância.'],
        [{ distance_km: 0 }, 'distance_km', 'A distância mínima é 1 km.'],
        [{ distance_km: 10.5 }, 'distance_km', 'Use um número inteiro de km.'],
        [{ estimated_duration_minutes: undefined }, 'estimated_duration_minutes', 'Informe a duração.'],
        [{ estimated_duration_minutes: 0 }, 'estimated_duration_minutes', 'A duração mínima é 1 minuto.'],
    ])('rejects %o', (overrides, field, message) => {
        expect(issues({ ...valid, stops: [], ...overrides })[field]).toBe(message);
    });

    it.each([
        [[stop('', 100, 90)], 'stops.0.city_name', 'Informe a cidade.'],
        [[{ city_name: 'Castro', estimated_minutes: 90 }], 'stops.0.cumulative_km', 'Informe o km.'],
        [[{ city_name: 'Castro', cumulative_km: 100 }], 'stops.0.estimated_minutes', 'Informe o tempo.'],
        [[stop('Castro', 0, 0)], 'stops.0.cumulative_km', 'Informe um km maior que 0.'],
        [[stop('A', 160, 150), stop('B', 115, 160)], 'stops.1.cumulative_km', 'Informe um km maior que 160.'],
        [[stop('A', 160, 150), stop('B', 160, 160)], 'stops.1.cumulative_km', 'Informe um km maior que 160.'],
        [[stop('A', 400, 380)], 'stops.0.cumulative_km', 'A parada fica antes do destino (km 400).'],
        [[stop('A', 100, 150), stop('B', 200, 90)], 'stops.1.estimated_minutes', 'Informe 150 min ou mais.'],
        [[stop('A', 100, 390)], 'stops.0.estimated_minutes', 'O tempo deve ser menor que a duração da rota.'],
    ])('rejects the stops %o', (stops, field, message) => {
        expect(issues({ ...valid, stops })[field]).toBe(message);
    });

    it('limits the number of stops', () => {
        const stops = Array.from({ length: MAX_LINE_STOPS + 1 }, (_, index) => stop(`Cidade ${index}`, index + 1, index + 1));

        expect(issues({ ...valid, stops }).stops).toBe('Uma rota aceita até 40 paradas.');
    });
});
