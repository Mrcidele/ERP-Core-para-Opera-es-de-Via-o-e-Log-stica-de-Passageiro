import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { RequestStatus } from '@/core/api/pagination';
import type { LinePayload } from '../schemas/lineSchema';
import type { Line } from '../types/trip';

const byRoute = (a: Line, b: Line): number =>
    a.origin.localeCompare(b.origin, 'pt-BR') || a.destination.localeCompare(b.destination, 'pt-BR');

export const useLineStore = defineStore('operations/lines', () => {
    // A relação de rotas é curta e também alimenta o formulário de viagem: vem inteira, sem paginação.
    const lines = ref<Line[]>([]);
    const listStatus = ref<RequestStatus>('idle');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    const removeStatus = ref<RequestStatus>('idle');
    const removeError = ref<string | null>(null);

    async function fetchAll(): Promise<void> {
        listStatus.value = 'loading';

        try {
            const response = await http.get<{ data: Line[] }>('/operations/lines');

            lines.value = response.data.data;
            listStatus.value = 'success';
        } catch {
            listStatus.value = 'error';
        }
    }

    function resetSaveState(): void {
        saveStatus.value = 'idle';
        validationErrors.value = {};
        saveError.value = null;
    }

    async function save(request: () => Promise<{ data: { data: Line } }>, failure: string): Promise<Line | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const line = (await request()).data.data;

            lines.value = [...lines.value.filter((item) => item.id !== line.id), line].sort(byRoute);
            saveStatus.value = 'success';

            return line;
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

    const create = (payload: LinePayload): Promise<Line | null> =>
        save(() => http.post('/operations/lines', payload), 'Não foi possível cadastrar a rota. Tente novamente.');

    const update = (id: string, payload: LinePayload): Promise<Line | null> =>
        save(() => http.put(`/operations/lines/${id}`, payload), 'Não foi possível salvar as alterações. Tente novamente.');

    function resetRemoveState(): void {
        removeStatus.value = 'idle';
        removeError.value = null;
    }

    async function remove(id: string): Promise<boolean> {
        removeStatus.value = 'loading';
        removeError.value = null;

        try {
            await http.delete(`/operations/lines/${id}`);

            lines.value = lines.value.filter((item) => item.id !== id);
            removeStatus.value = 'success';

            return true;
        } catch (error) {
            // Rota com viagem agendada ou em andamento: a API explica o motivo no campo `line`.
            removeError.value = extractValidationErrors(error)?.line?.[0] ?? extractErrorMessage(error, 'Não foi possível excluir a rota. Tente novamente.');
            removeStatus.value = 'error';

            return false;
        }
    }

    return {
        lines,
        listStatus,
        saveStatus,
        validationErrors,
        saveError,
        removeStatus,
        removeError,
        fetchAll,
        resetSaveState,
        create,
        update,
        resetRemoveState,
        remove,
    };
});
