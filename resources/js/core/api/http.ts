import axios, { isAxiosError } from 'axios';

export const http = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

/**
 * Ligação com a sessão e com os avisos globais, feita em core/session. Fica aqui como funções
 * para o cliente HTTP não depender da store de autenticação nem do router (que dependem dele).
 */
export interface HttpSessionHooks {
    /** Token da sessão atual, ou null se ninguém entrou. */
    getToken: () => string | null;
    /** A API respondeu 401: a sessão acabou ou o token não vale mais. */
    onUnauthorized: () => void;
    /** A API respondeu 403: o usuário não tem permissão para a ação. */
    onForbidden: (message: string) => void;
    /** A API respondeu 409: a ação conflita com o estado atual do registro. */
    onConflict: (message: string) => void;
    /** A API respondeu 422: dados recusados por validação ou por regra de negócio. */
    onValidationFailed: (message: string) => void;
}

const idleHooks: HttpSessionHooks = {
    getToken: () => null,
    onUnauthorized: () => undefined,
    onForbidden: () => undefined,
    onConflict: () => undefined,
    onValidationFailed: () => undefined,
};

let hooks: HttpSessionHooks = idleHooks;

export function configureHttp(sessionHooks: Partial<HttpSessionHooks>): void {
    hooks = { ...idleHooks, ...sessionHooks };
}

export function resetHttp(): void {
    hooks = idleHooks;
}

export const VALIDATION_FALLBACK_MESSAGE = 'Revise os campos destacados e tente de novo.';

/**
 * Texto do aviso de um 422. Uma regra de negócio recusa com um erro só, já escrito para o usuário;
 * vários erros de campo viram um aviso genérico, porque cada um aparece no próprio campo.
 */
function validationSummary(error: unknown): string {
    const messages = Object.values(extractValidationErrors(error) ?? {}).flat();

    return messages.length === 1 && typeof messages[0] === 'string' ? messages[0] : VALIDATION_FALLBACK_MESSAGE;
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
        switch (error.response?.status) {
            case 401:
                hooks.onUnauthorized();
                break;
            case 403:
                hooks.onForbidden(extractErrorMessage(error, 'Você não tem permissão para executar esta ação.'));
                break;
            case 409:
                hooks.onConflict(extractErrorMessage(error, 'A ação conflita com o estado atual do registro. Atualize a tela e tente de novo.'));
                break;
            case 422:
                hooks.onValidationFailed(validationSummary(error));
                break;
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
