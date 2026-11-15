import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { LoginPayload } from '../schemas/loginSchema';
import type { AuthenticatedUser, AuthSession, AuthUser, Permission } from '../types/auth';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const AUTH_STORAGE_KEY = 'erp.auth';

interface StoredSession {
    token: string;
    user: AuthUser;
    permissions: Permission[];
}

function readStoredSession(): StoredSession | null {
    try {
        const raw = localStorage.getItem(AUTH_STORAGE_KEY);

        if (raw === null) {
            return null;
        }

        const parsed: unknown = JSON.parse(raw);

        if (
            typeof parsed === 'object' &&
            parsed !== null &&
            'token' in parsed &&
            typeof parsed.token === 'string' &&
            'user' in parsed &&
            typeof parsed.user === 'object' &&
            parsed.user !== null &&
            'permissions' in parsed &&
            Array.isArray(parsed.permissions)
        ) {
            return parsed as StoredSession;
        }
    } catch {
        // Armazenamento indisponível ou conteúdo corrompido: segue sem sessão.
    }

    return null;
}

function writeStoredSession(session: StoredSession | null): void {
    try {
        if (session === null) {
            localStorage.removeItem(AUTH_STORAGE_KEY);
        } else {
            localStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(session));
        }
    } catch {
        // Sem armazenamento, a sessão vale só até a página ser recarregada.
    }
}

export const useAuthStore = defineStore('auth', () => {
    const stored = readStoredSession();

    const token = ref<string | null>(stored?.token ?? null);
    const user = ref<AuthUser | null>(stored?.user ?? null);
    const permissions = ref<Permission[]>(stored?.permissions ?? []);

    const loginStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const loginError = ref<string | null>(null);

    const isAuthenticated = computed(() => token.value !== null && user.value !== null);

    /**
     * O usuário tem a permissão? Serve só para esconder o que ele não pode usar:
     * quem decide de verdade é a API, que recusa com 403.
     */
    function can(permission: string): boolean {
        return isAuthenticated.value && (permissions.value as string[]).includes(permission);
    }

    function setSession(session: StoredSession): void {
        token.value = session.token;
        user.value = session.user;
        permissions.value = session.permissions;
        writeStoredSession(session);
    }

    function clearSession(): void {
        token.value = null;
        user.value = null;
        permissions.value = [];
        writeStoredSession(null);
    }

    async function login(payload: LoginPayload): Promise<boolean> {
        loginStatus.value = 'loading';
        validationErrors.value = {};
        loginError.value = null;

        try {
            const response = await http.post<{ data: AuthSession }>('/auth/login', payload);

            setSession(response.data.data);
            loginStatus.value = 'success';

            return true;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                loginError.value = extractErrorMessage(error, 'Não foi possível entrar. Tente novamente.');
            }

            loginStatus.value = 'error';

            return false;
        }
    }

    /** Encerra a sessão aqui mesmo que a API não responda: o token local é sempre descartado. */
    async function logout(): Promise<void> {
        try {
            await http.post('/auth/logout');
        } catch {
            // Token já inválido ou sem conexão: não impede a saída.
        } finally {
            clearSession();
        }
    }

    /** Atualiza usuário e permissões com o que a API diz agora (o papel pode ter mudado desde o login). */
    async function refresh(): Promise<void> {
        const currentToken = token.value;

        if (currentToken === null) {
            return;
        }

        try {
            const response = await http.get<{ data: AuthenticatedUser }>('/auth/me');

            // Só aplica se a sessão ainda é a mesma (o 401 do interceptor já pode tê-la encerrado).
            if (token.value === currentToken) {
                setSession({ token: currentToken, ...response.data.data });
            }
        } catch {
            // 401 é tratado pelo interceptor; outras falhas mantêm o que estava salvo.
        }
    }

    return {
        token,
        user,
        permissions,
        loginStatus,
        validationErrors,
        loginError,
        isAuthenticated,
        can,
        setSession,
        clearSession,
        login,
        logout,
        refresh,
    };
});
