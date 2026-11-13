import axios, { isAxiosError } from 'axios';

export const http = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
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
