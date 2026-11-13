import { z } from 'zod';
import { isValidCpf, onlyDigits } from '@/core/utils/cpf';
import { CNH_CATEGORIES, EMPLOYEE_ROLES, EMPLOYEE_STATUSES, type CnhCategory } from '../types/employee';

// Mesmas regras de CreateEmployeeRequest e RegisterEmployeeAction do backend.

/** Hoje no fuso do navegador, no formato AAAA-MM-DD. */
export function todayIso(now: Date = new Date()): string {
    const pad = (value: number): string => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function isRealDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);

    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

const isCnhCategory = (value: string): value is CnhCategory => (CNH_CATEGORIES as readonly string[]).includes(value);

export const createEmployeeSchema = z
    .object({
        name: z.string().trim().min(3, 'Informe o nome completo (mínimo de 3 caracteres).').max(120, 'O nome pode ter no máximo 120 caracteres.'),
        cpf: z.string().transform(onlyDigits).refine(isValidCpf, 'O CPF informado não é válido.'),
        role: z.enum(EMPLOYEE_ROLES, 'Selecione o cargo.'),
        status: z.enum(EMPLOYEE_STATUSES, 'Selecione o status.'),
        cnh_number: z.string().transform(onlyDigits),
        cnh_category: z.string().transform((value) => value.trim().toUpperCase()),
        cnh_expiration_date: z.string().trim(),
    })
    .superRefine((data, ctx) => {
        const isDriver = data.role === 'driver';

        if (data.cnh_number === '') {
            if (isDriver) {
                ctx.addIssue({ code: 'custom', path: ['cnh_number'], message: 'Informe o número da CNH do motorista.' });
            }
        } else if (!/^\d{11}$/.test(data.cnh_number)) {
            ctx.addIssue({ code: 'custom', path: ['cnh_number'], message: 'O número da CNH deve ter 11 dígitos.' });
        }

        if (data.cnh_category === '') {
            if (isDriver) {
                ctx.addIssue({ code: 'custom', path: ['cnh_category'], message: 'Informe a categoria da CNH do motorista.' });
            }
        } else if (!isCnhCategory(data.cnh_category)) {
            ctx.addIssue({ code: 'custom', path: ['cnh_category'], message: 'Selecione uma categoria de CNH válida.' });
        }

        if (data.cnh_expiration_date === '') {
            if (isDriver) {
                ctx.addIssue({ code: 'custom', path: ['cnh_expiration_date'], message: 'Informe a validade da CNH do motorista.' });
            }
        } else if (!isRealDate(data.cnh_expiration_date)) {
            ctx.addIssue({ code: 'custom', path: ['cnh_expiration_date'], message: 'Informe uma data válida.' });
        } else if (data.cnh_expiration_date <= todayIso()) {
            ctx.addIssue({
                code: 'custom',
                path: ['cnh_expiration_date'],
                message: 'A CNH está vencida ou vence hoje. Informe uma validade posterior a hoje.',
            });
        }
    })
    .transform((data) => ({
        ...data,
        // Campos de CNH vazios vão como null, como a API espera.
        cnh_number: data.cnh_number === '' ? null : data.cnh_number,
        cnh_category: data.cnh_category === '' ? null : (data.cnh_category as CnhCategory),
        cnh_expiration_date: data.cnh_expiration_date === '' ? null : data.cnh_expiration_date,
    }));

export type CreateEmployeePayload = z.output<typeof createEmployeeSchema>;
export type EmployeeField = keyof CreateEmployeePayload;
