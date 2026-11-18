import { z } from 'zod';
import { USER_ROLES } from '@/modules/auth/types/auth';

// Mesmas regras de UserRules, CreateUserRequest e UpdateUserRequest do backend.
const profile = {
    name: z.string().trim().min(1, 'Informe o nome.').max(255, 'O nome pode ter no máximo 255 caracteres.'),
    email: z
        .string()
        .trim()
        .min(1, 'Informe o e-mail.')
        .max(254, 'O e-mail pode ter no máximo 254 caracteres.')
        .pipe(z.email('Informe um e-mail válido.')),
    role: z.enum(USER_ROLES, 'Selecione o perfil de acesso.'),
};

const strongPassword = z
    .string()
    .min(12, 'A senha precisa de pelo menos 12 caracteres.')
    .max(128, 'A senha pode ter no máximo 128 caracteres.')
    .regex(/[a-z]/, 'A senha precisa de letras minúsculas.')
    .regex(/[A-Z]/, 'A senha precisa de letras maiúsculas.')
    .regex(/[0-9]/, 'A senha precisa de números.');

const confirmation = <T extends { password: string; password_confirmation: string }>(data: T, ctx: z.RefinementCtx): void => {
    if (data.password !== '' && data.password !== data.password_confirmation) {
        ctx.addIssue({ code: 'custom', path: ['password_confirmation'], message: 'As senhas não conferem.' });
    }
};

export const createUserSchema = z
    .object({ ...profile, password: strongPassword, password_confirmation: z.string() })
    .superRefine(confirmation);

/** Na edição, a senha é opcional: vazia mantém a atual. */
export const updateUserSchema = z
    .object({ ...profile, password: z.union([z.literal(''), strongPassword]), password_confirmation: z.string() })
    .superRefine(confirmation)
    .transform(({ password, password_confirmation, ...rest }) =>
        password === '' ? rest : { ...rest, password, password_confirmation },
    );

export type CreateUserPayload = z.output<typeof createUserSchema>;
export type UpdateUserPayload = z.output<typeof updateUserSchema>;
export type UserField = keyof z.input<typeof createUserSchema>;
