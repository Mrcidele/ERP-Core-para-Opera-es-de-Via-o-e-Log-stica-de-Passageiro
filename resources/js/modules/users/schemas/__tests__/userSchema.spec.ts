import { describe, expect, it } from 'vitest';
import { createUserSchema, updateUserSchema } from '../userSchema';

const valid = { name: 'Carla', email: 'carla@erp.test', role: 'dispatcher', password: 'Senha-Segura-2026', password_confirmation: 'Senha-Segura-2026' };

const messages = (result: { success: boolean; error?: { issues: { message: string }[] } }): string[] =>
    result.success ? [] : (result.error?.issues.map((issue) => issue.message) ?? []);

describe('createUserSchema', () => {
    it('accepts a valid user, trimming name and e-mail', () => {
        expect(createUserSchema.parse({ ...valid, name: '  Carla ', email: ' carla@erp.test ' })).toEqual(valid);
    });

    it.each([
        ['short', 'Curta-1', 'A senha precisa de pelo menos 12 caracteres.'],
        ['no upper case', 'senha-segura-2026', 'A senha precisa de letras maiúsculas.'],
        ['no lower case', 'SENHA-SEGURA-2026', 'A senha precisa de letras minúsculas.'],
        ['no digit', 'Senha-Segura-Forte', 'A senha precisa de números.'],
    ])('rejects a password with %s', (_, password, message) => {
        expect(messages(createUserSchema.safeParse({ ...valid, password, password_confirmation: password }))).toContain(message);
    });

    it('requires the confirmation to match, a role and a valid e-mail', () => {
        expect(messages(createUserSchema.safeParse({ ...valid, password_confirmation: 'Outra-Senha-2026' }))).toEqual(['As senhas não conferem.']);
        expect(messages(createUserSchema.safeParse({ ...valid, role: '' }))).toEqual(['Selecione o perfil de acesso.']);
        expect(messages(createUserSchema.safeParse({ ...valid, email: 'carla' }))).toEqual(['Informe um e-mail válido.']);
    });
});

describe('updateUserSchema', () => {
    it('drops a blank password so the current one is kept', () => {
        expect(updateUserSchema.parse({ ...valid, password: '', password_confirmation: '' })).toEqual({ name: 'Carla', email: 'carla@erp.test', role: 'dispatcher' });
    });

    it('sends a new password when it is filled and confirmed', () => {
        expect(updateUserSchema.parse(valid)).toEqual(valid);
    });

    it('still checks a new password', () => {
        expect(messages(updateUserSchema.safeParse({ ...valid, password: 'curta', password_confirmation: 'curta' }))).toContain('A senha precisa de pelo menos 12 caracteres.');
    });
});
