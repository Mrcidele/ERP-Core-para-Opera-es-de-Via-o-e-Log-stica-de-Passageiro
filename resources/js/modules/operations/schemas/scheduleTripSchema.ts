import { z } from 'zod';

// Mesmas regras de ScheduleTripRequest e ScheduleTripAction do backend.
// Os campos de data chegam do <input type="datetime-local"> (AAAA-MM-DDTHH:mm, no fuso do navegador)
// e saem em ISO 8601 UTC, que é o que a API aceita.

const LOCAL_DATE_TIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/;

function toDate(value: string): Date | null {
    if (!LOCAL_DATE_TIME.test(value)) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export const scheduleTripSchema = z
    .object({
        line_id: z.string().min(1, 'Selecione a linha.'),
        vehicle_id: z.string().min(1, 'Selecione o veículo.'),
        driver_id: z.string().min(1, 'Selecione o motorista.'),
        departure_time: z.string(),
        arrival_time: z.string(),
    })
    .superRefine((data, ctx) => {
        const departure = toDate(data.departure_time);
        const arrival = toDate(data.arrival_time);

        if (departure === null) {
            ctx.addIssue({ code: 'custom', path: ['departure_time'], message: 'Informe a data e a hora da partida.' });
        } else if (departure.getTime() <= Date.now()) {
            ctx.addIssue({ code: 'custom', path: ['departure_time'], message: 'A partida deve ser no futuro.' });
        }

        if (arrival === null) {
            ctx.addIssue({ code: 'custom', path: ['arrival_time'], message: 'Informe a data e a hora da chegada.' });
        } else if (departure !== null && arrival.getTime() <= departure.getTime()) {
            ctx.addIssue({ code: 'custom', path: ['arrival_time'], message: 'A chegada deve ser depois da partida.' });
        }
    })
    .transform((data) => ({
        ...data,
        departure_time: new Date(data.departure_time).toISOString(),
        arrival_time: new Date(data.arrival_time).toISOString(),
    }));

export type ScheduleTripPayload = z.output<typeof scheduleTripSchema>;
export type TripField = keyof ScheduleTripPayload;
