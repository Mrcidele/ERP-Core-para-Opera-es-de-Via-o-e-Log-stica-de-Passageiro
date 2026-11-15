import { AxiosError, type AxiosAdapter, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { configureHttp, http } from '../http';

/** Faz o Axios responder com o status informado, sem rede, e guarda a requisição recebida. */
function respondWith(status: number, data: unknown = {}): { lastRequest: () => InternalAxiosRequestConfig | undefined } {
    let last: InternalAxiosRequestConfig | undefined;

    const adapter: AxiosAdapter = (config) => {
        last = config;

        const response: AxiosResponse = { data, status, statusText: '', headers: {}, config };

        return status >= 200 && status < 300
            ? Promise.resolve(response)
            : Promise.reject(new AxiosError(`Request failed with status code ${status}`, String(status), config, null, response));
    };

    http.defaults.adapter = adapter;

    return { lastRequest: () => last };
}

describe('http session hooks', () => {
    const originalAdapter = http.defaults.adapter;
    const onUnauthorized = vi.fn();
    const onForbidden = vi.fn();
    let token: string | null;

    beforeEach(() => {
        token = '1|plain-token';
        onUnauthorized.mockReset();
        onForbidden.mockReset();
        configureHttp({ getToken: () => token, onUnauthorized, onForbidden });
    });

    afterEach(() => {
        if (originalAdapter !== undefined) {
            http.defaults.adapter = originalAdapter;
        }
    });

    it('sends the bearer token on every request', async () => {
        const server = respondWith(200, { data: [] });

        await http.get('/fleet/vehicles');
        await http.post('/operations/trips', {});

        expect(server.lastRequest()?.headers.get('Authorization')).toBe('Bearer 1|plain-token');
    });

    it('reads the token at request time, so a new login is used right away', async () => {
        const server = respondWith(200);

        token = '2|newer-token';
        await http.get('/auth/me');

        expect(server.lastRequest()?.headers.get('Authorization')).toBe('Bearer 2|newer-token');
    });

    it('sends no Authorization header without a session', async () => {
        const server = respondWith(200);

        token = null;
        await http.post('/auth/login', {});

        expect(server.lastRequest()?.headers.has('Authorization')).toBe(false);
    });

    it('ends the session on 401 and still rejects the request', async () => {
        respondWith(401, { message: 'Não autenticado. Entre no sistema para continuar.' });

        await expect(http.get('/fleet/vehicles')).rejects.toMatchObject({ response: { status: 401 } });

        expect(onUnauthorized).toHaveBeenCalledTimes(1);
        expect(onForbidden).not.toHaveBeenCalled();
    });

    it('reports access denied on 403 with the message from the API and keeps the session', async () => {
        respondWith(403, { message: 'Você não tem permissão para executar esta ação.' });

        await expect(http.delete('/fleet/vehicles/1')).rejects.toMatchObject({ response: { status: 403 } });

        expect(onForbidden).toHaveBeenCalledWith('Você não tem permissão para executar esta ação.');
        expect(onUnauthorized).not.toHaveBeenCalled();
    });

    it('uses a default access denied message when the API sends none', async () => {
        respondWith(403, '');

        await expect(http.get('/audit-logs')).rejects.toBeDefined();

        expect(onForbidden).toHaveBeenCalledWith('Você não tem permissão para executar esta ação.');
    });

    it.each([404, 422, 429, 500])('leaves a %i response to the caller', async (status) => {
        respondWith(status, { message: 'x' });

        await expect(http.get('/fleet/vehicles')).rejects.toMatchObject({ response: { status } });

        expect(onUnauthorized).not.toHaveBeenCalled();
        expect(onForbidden).not.toHaveBeenCalled();
    });
});
