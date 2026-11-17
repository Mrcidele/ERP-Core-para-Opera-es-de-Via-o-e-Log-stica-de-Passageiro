import { z } from 'zod';
import { REAIS, reaisToCents } from '@/core/utils/money';
import { MAINTENANCE_TYPES } from '../types/maintenance';

// Mesmas regras de OpenMaintenanceOrderRequest e CloseMaintenanceOrderRequest do backend.

const LOCAL_DATE_TIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/;

// A previsão de término é opcional. Chega do <input type="datetime-local"> (fuso do navegador) e vai
// para a API em ISO 8601 UTC; a API confere que é posterior à abertura, que é o instante do envio.
export const openMaintenanceOrderSchema = z
    .object({
        vehicle_id: z.string().min(1, 'Selecione o veículo.'),
        mechanic_id: z.string().min(1, 'Selecione o mecânico.'),
        type: z.enum(MAINTENANCE_TYPES, 'Selecione o tipo de manutenção.'),
        description: z
            .string()
            .trim()
            .min(5, 'Descreva o serviço (mínimo de 5 caracteres).')
            .max(2000, 'A descrição pode ter no máximo 2000 caracteres.'),
        estimated_completion_at: z.string().refine((value) => value === '' || LOCAL_DATE_TIME.test(value), 'Informe a data e a hora da previsão.'),
    })
    .superRefine((data, ctx) => {
        if (data.estimated_completion_at !== '' && new Date(data.estimated_completion_at).getTime() <= Date.now()) {
            ctx.addIssue({ code: 'custom', path: ['estimated_completion_at'], message: 'A previsão de término deve estar no futuro.' });
        }
    })
    .transform((data) => ({
        ...data,
        estimated_completion_at: data.estimated_completion_at === '' ? null : new Date(data.estimated_completion_at).toISOString(),
    }));

export type OpenMaintenanceOrderPayload = z.output<typeof openMaintenanceOrderSchema>;
export type OpenMaintenanceOrderField = keyof OpenMaintenanceOrderPayload;

const MAX_COST_CENTS = 2_000_000_000;

// Usada pelos testes e por quem já importava daqui.
export { reaisToCents };

export const completeMaintenanceOrderSchema = z
    .object({
        cost: z
            .string()
            .trim()
            .min(1, 'Informe o custo final (use 0 se não houve custo).')
            .regex(REAIS, 'Informe o valor em reais, como 450,90.'),
    })
    .transform((data) => ({ status: 'completed' as const, cost_cents: reaisToCents(data.cost) }))
    .refine((data) => data.cost_cents <= MAX_COST_CENTS, { path: ['cost'], message: 'O valor é alto demais.' });

export type CompleteMaintenanceOrderPayload = z.output<typeof completeMaintenanceOrderSchema>;
