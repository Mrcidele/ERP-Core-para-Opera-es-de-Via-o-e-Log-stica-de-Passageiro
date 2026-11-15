import axios, { isAxiosError } from 'axios';

export const http = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

/**
 * Ligação com a sessão, feita em core/session. Fica aqui como funções para o cliente HTTP
 * não depender da store de autenticação nem do router (que dependem dele).
 */
export interface HttpSessionHooks {
    /** Token da sessão atual, ou null se ninguém entrou. */
    getToken: () => string | null;
    /** A API respondeu 401: a sessão acabou ou o token não vale mais. */
    onUnauthorized: () => void;
    /** A API respondeu 403: o usuário não tem permissão para a ação. */
    onForbidden: (message: string) => void;
}

const idleHooks: HttpSessionHooks = { getToken: () => null, onUnauthorized: () => undefined, onForbidden: () => undefined };

let hooks: HttpSessionHooks = idleHooks;

export function configureHttp(sessionHooks: HttpSessionHooks): void {
    hooks = sessionHooks;
}

export function resetHttp(): void {
    hooks = idleHooks;
}

http.interceptors.request.use((config) => {
    const token = hooks.getToken();

    if (token !== null) {
        config.headers.set('Authorization', `Bearer ${token}`);
    }

    return config;
});

http.interceptors.response.use(undefined, (error: unknown) => {
    if (isAxiosError(error)) {
        if (error.response?.status === 401) {
            hooks.onUnauthorized();
        } else if (error.response?.status === 403) {
            hooks.onForbidden(extractErrorMessage(error, 'Você não tem permissão para executar esta ação.'));
        }
    }

    return Promise.reject(error);
});

export type ValidationErrors = Record<string, string[]>;

/** Extrai os erros de campo de uma resposta 422 do Laravel; null se o erro for de outro tipo. */
export function extractValidationErrors(error: unknown): ValidationErrors | null {
    if (!isAxiosError(error) || error.response?.status !== 422) {
        return null;
    }

    const data: unknown = error.response.data;

    if (typeof data !== 'object' || data === null || !('errors' in data)) {
        return null;
    }

    return data.errors as ValidationErrors;
}

/** Mensagem para exibir ao usuário: a da API quando houver (4xx), senão a alternativa informada. */
export function extractErrorMessage(error: unknown, fallback: string): string {
    if (isAxiosError(error) && error.response && error.response.status < 500) {
        const data: unknown = error.response.data;

        if (typeof data === 'object' && data !== null && 'message' in data && typeof data.message === 'string') {
            return data.message;
        }
    }

    return fallback;
}
