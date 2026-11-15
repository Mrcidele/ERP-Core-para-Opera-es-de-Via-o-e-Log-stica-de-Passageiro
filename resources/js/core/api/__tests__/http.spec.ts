import { AxiosError, type AxiosAdapter, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { configureHttp, http, VALIDATION_FALLBACK_MESSAGE } from '../http';

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
    const onConflict = vi.fn();
    const onValidationFailed = vi.fn();
    let token: string | null;

    beforeEach(() => {
        token = '1|plain-token';
        onUnauthorized.mockReset();
        onForbidden.mockReset();
        onConflict.mockReset();
        onValidationFailed.mockReset();
        configureHttp({ getToken: () => token, onUnauthorized, onForbidden, onConflict, onValidationFailed });
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

    it('reports a conflict on 409 with the message from the API', async () => {
        respondWith(409, { message: 'A viagem já foi iniciada por outra pessoa.' });

        await expect(http.post('/operations/trips/1/start')).rejects.toMatchObject({ response: { status: 409 } });

        expect(onConflict).toHaveBeenCalledWith('A viagem já foi iniciada por outra pessoa.');
    });

    it('reports a business rule refused with 422 using its own message', async () => {
        respondWith(422, { message: 'x', errors: { driver_id: ['O motorista precisa de pelo menos 11 horas de descanso entre o fim de uma viagem e o início da seguinte.'] } });

        await expect(http.post('/operations/trips', {})).rejects.toMatchObject({ response: { status: 422 } });

        expect(onValidationFailed).toHaveBeenCalledWith('O motorista precisa de pelo menos 11 horas de descanso entre o fim de uma viagem e o início da seguinte.');
    });

    it('summarizes several field errors on 422 instead of repeating each one', async () => {
        respondWith(422, { message: 'The plate field is required. (and 1 more error)', errors: { plate: ['a'], chassis: ['b'] } });

        await expect(http.post('/fleet/vehicles', {})).rejects.toBeDefined();

        expect(onValidationFailed).toHaveBeenCalledWith(VALIDATION_FALLBACK_MESSAGE);
    });

    it('works with only some hooks configured', async () => {
        configureHttp({ getToken: () => 'only-token' });
        const server = respondWith(403, {});

        await expect(http.get('/audit-logs')).rejects.toBeDefined();

        expect(server.lastRequest()?.headers.get('Authorization')).toBe('Bearer only-token');
    });

    it.each([404, 422, 429, 500])('leaves a %i response to the caller', async (status) => {
        respondWith(status, { message: 'x' });

        await expect(http.get('/fleet/vehicles')).rejects.toMatchObject({ response: { status } });

        expect(onUnauthorized).not.toHaveBeenCalled();
        expect(onForbidden).not.toHaveBeenCalled();
    });
});
