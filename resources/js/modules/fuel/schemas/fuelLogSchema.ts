import { z } from 'zod';
import { LITERS, litersToMilliliters, REAIS, reaisToCents } from '@/core/utils/money';

// Mesmas regras de RecordFuelLogRequest e de RecordFuelLogAction (data no passado) do backend.
// O volume é digitado em litros e o valor em reais; para a API vão mililitros e centavos.
// A data chega do <input type="datetime-local"> (fuso do navegador) e sai em ISO 8601 UTC.

const LOCAL_DATE_TIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/;
const MAX_MILLILITERS = 5_000_000;
const MAX_COST_CENTS = 1_000_000_000;

export const fuelLogSchema = z
    .object({
        vehicle_id: z.string().min(1, 'Selecione o veículo.'),
        odometer_km: z
            .number('Informe o hodômetro.')
            .int('O hodômetro deve ser um número inteiro de km.')
            .min(0, 'O hodômetro não pode ser negativo.')
            .max(2_000_000_000, 'O hodômetro é alto demais.'),
        liters: z.string().trim().min(1, 'Informe o volume abastecido.').regex(LITERS, 'Informe o volume em litros, como 350,5.'),
        total_cost: z.string().trim().min(1, 'Informe o valor total.').regex(REAIS, 'Informe o valor em reais, como 2.099,50.'),
        station_name: z.string().trim().min(1, 'Informe o posto.').max(120, 'O nome do posto é longo demais.'),
        fueled_at: z.string().regex(LOCAL_DATE_TIME, 'Informe a data e a hora do abastecimento.'),
    })
    .superRefine((data, ctx) => {
        const milliliters = litersToMilliliters(data.liters);

        if (milliliters < 1) {
            ctx.addIssue({ code: 'custom', path: ['liters'], message: 'O volume deve ser maior que zero.' });
        } else if (milliliters > MAX_MILLILITERS) {
            ctx.addIssue({ code: 'custom', path: ['liters'], message: 'O volume passa de 5.000 litros.' });
        }

        if (reaisToCents(data.total_cost) > MAX_COST_CENTS) {
            ctx.addIssue({ code: 'custom', path: ['total_cost'], message: 'O valor é alto demais.' });
        }

        const fueledAt = new Date(data.fueled_at);

        if (Number.isNaN(fueledAt.getTime())) {
            ctx.addIssue({ code: 'custom', path: ['fueled_at'], message: 'Informe a data e a hora do abastecimento.' });
        } else if (fueledAt.getTime() > Date.now()) {
            ctx.addIssue({ code: 'custom', path: ['fueled_at'], message: 'A data do abastecimento não pode estar no futuro.' });
        }
    })
    .transform((data) => ({
        vehicle_id: data.vehicle_id,
        odometer_km: data.odometer_km,
        liters_milliliters: litersToMilliliters(data.liters),
        total_cost_cents: reaisToCents(data.total_cost),
        station_name: data.station_name,
        fueled_at: new Date(data.fueled_at).toISOString(),
    }));

export type FuelLogPayload = z.output<typeof fuelLogSchema>;
/** Campos do formulário; os erros da API chegam com o nome do campo enviado. */
export type FuelLogFormField = keyof z.input<typeof fuelLogSchema>;
