import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import { usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { CreateUserPayload, UpdateUserPayload } from '../schemas/userSchema';
import type { ManagedUser } from '../types/user';

export const useUserStore = defineStore('identity/users', () => {
    // Lista paginada no servidor, com busca por nome ou e-mail e filtro ativo/desativado.
    const list = usePagedList<ManagedUser>('/identity/users');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    const toggleStatus = ref<RequestStatus>('idle');
    const toggleError = ref<string | null>(null);

    function resetSaveState(): void {
        saveStatus.value = 'idle';
        validationErrors.value = {};
        saveError.value = null;
    }

    async function save(request: () => Promise<{ data: { data: ManagedUser } }>, failure: string): Promise<ManagedUser | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const user = (await request()).data.data;

            saveStatus.value = 'success';
            await list.fetchPage();

            return user;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                saveError.value = extractErrorMessage(error, failure);
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    const create = (payload: CreateUserPayload): Promise<ManagedUser | null> =>
        save(() => http.post('/identity/users', payload), 'Não foi possível criar o usuário. Tente novamente.');

    const update = (id: string, payload: UpdateUserPayload): Promise<ManagedUser | null> =>
        save(() => http.put(`/identity/users/${id}`, payload), 'Não foi possível salvar as alterações. Tente novamente.');

    /** Desativa ou reativa; a mensagem da API (próprio acesso, último administrador) aparece no diálogo. */
    async function setActive(user: ManagedUser, active: boolean): Promise<ManagedUser | null> {
        toggleStatus.value = 'loading';
        toggleError.value = null;

        try {
            const response = await http.post<{ data: ManagedUser }>(`/identity/users/${user.id}/${active ? 'reactivate' : 'deactivate'}`);

            toggleStatus.value = 'success';
            await list.fetchPage();

            return response.data.data;
        } catch (error) {
            const errors = extractValidationErrors(error);
            toggleError.value = Object.values(errors ?? {})[0]?.[0] ?? extractErrorMessage(error, 'Não foi possível alterar o acesso. Tente novamente.');
            toggleStatus.value = 'error';

            return null;
        }
    }

    function resetToggleState(): void {
        toggleStatus.value = 'idle';
        toggleError.value = null;
    }

    return {
        users: list.items,
        meta: list.meta,
        search: list.search,
        status: list.status,
        listStatus: list.listStatus,
        refreshing: list.refreshing,
        filtering: list.filtering,
        fetchAll: list.fetchPage,
        applyFilters: list.applyFilters,
        saveStatus,
        validationErrors,
        saveError,
        toggleStatus,
        toggleError,
        resetSaveState,
        resetToggleState,
        create,
        update,
        setActive,
    };
});
