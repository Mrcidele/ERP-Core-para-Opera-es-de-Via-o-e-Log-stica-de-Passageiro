import { z } from 'zod';

// Mesmas regras de SaveLineRequest e de Line::define no backend. A ordem do array é a sequência das paradas.
export const MAX_LINE_STOPS = 40;

const km = (required: string) =>
    z.number(required).int('Use um número inteiro de km.').min(0, 'O km não pode ser negativo.').max(10_000, 'A distância é alta demais.');

const minutes = (required: string) =>
    z.number(required).int('Use um número inteiro de minutos.').min(0, 'O tempo não pode ser negativo.').max(10_080, 'O tempo é alto demais.');

const stopSchema = z.object({
    city_name: z.string().trim().min(1, 'Informe a cidade.').max(120, 'O nome da cidade é longo demais.'),
    cumulative_km: km('Informe o km.'),
    estimated_minutes: minutes('Informe o tempo.'),
});

export const lineSchema = z
    .object({
        origin: z.string().trim().min(1, 'Informe a cidade de origem.').max(120, 'O nome da cidade é longo demais.'),
        destination: z.string().trim().min(1, 'Informe a cidade de destino.').max(120, 'O nome da cidade é longo demais.'),
        distance_km: km('Informe a distância.').min(1, 'A distância mínima é 1 km.'),
        estimated_duration_minutes: minutes('Informe a duração.').min(1, 'A duração mínima é 1 minuto.'),
        stops: z.array(stopSchema).max(MAX_LINE_STOPS, `Uma rota aceita até ${MAX_LINE_STOPS} paradas.`),
    })
    .superRefine((line, ctx) => {
        if (line.origin.toLocaleLowerCase('pt-BR') === line.destination.toLocaleLowerCase('pt-BR')) {
            ctx.addIssue({ code: 'custom', path: ['destination'], message: 'O destino deve ser diferente da origem.' });
        }

        let previousKm = 0;
        let previousMinutes = 0;

        // Cada parada fica depois da anterior e antes do destino, em distância e em tempo.
        line.stops.forEach((stop, index) => {
            if (stop.cumulative_km <= previousKm) {
                ctx.addIssue({ code: 'custom', path: ['stops', index, 'cumulative_km'], message: `Informe um km maior que ${previousKm}.` });
            } else if (stop.cumulative_km >= line.distance_km) {
                ctx.addIssue({ code: 'custom', path: ['stops', index, 'cumulative_km'], message: `A parada fica antes do destino (km ${line.distance_km}).` });
            }

            if (stop.estimated_minutes < previousMinutes) {
                ctx.addIssue({ code: 'custom', path: ['stops', index, 'estimated_minutes'], message: `Informe ${previousMinutes} min ou mais.` });
            } else if (stop.estimated_minutes >= line.estimated_duration_minutes) {
                ctx.addIssue({ code: 'custom', path: ['stops', index, 'estimated_minutes'], message: 'O tempo deve ser menor que a duração da rota.' });
            }

            previousKm = stop.cumulative_km;
            previousMinutes = stop.estimated_minutes;
        });
    });

export type LinePayload = z.output<typeof lineSchema>;
