import { z } from 'zod';
import { REAIS, reaisToCents } from '@/core/utils/money';
import { MAINTENANCE_TYPES } from '../types/maintenance';

// Mesmas regras de OpenMaintenanceOrderRequest e CloseMaintenanceOrderRequest do backend.

export const openMaintenanceOrderSchema = z.object({
    vehicle_id: z.string().min(1, 'Selecione o veículo.'),
    mechanic_id: z.string().min(1, 'Selecione o mecânico.'),
    type: z.enum(MAINTENANCE_TYPES, 'Selecione o tipo de manutenção.'),
    description: z
        .string()
        .trim()
        .min(5, 'Descreva o serviço (mínimo de 5 caracteres).')
        .max(2000, 'A descrição pode ter no máximo 2000 caracteres.'),
});

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
