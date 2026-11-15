import { z } from 'zod';

// Mesmas regras de LoginRequest do backend.
export const loginSchema = z.object({
    email: z.string().trim().min(1, 'Informe o e-mail.').max(254, 'O e-mail é longo demais.').pipe(z.email('Informe um e-mail válido.')),
    password: z.string().min(1, 'Informe a senha.').max(200, 'A senha é longa demais.'),
});

export type LoginPayload = z.output<typeof loginSchema>;
export type LoginField = keyof LoginPayload;
