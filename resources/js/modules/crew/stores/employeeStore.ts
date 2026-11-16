import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import { usePagedList, type RequestStatus } from '@/core/api/pagination';
import type { CreateEmployeePayload } from '../schemas/createEmployeeSchema';
import type { Employee } from '../types/employee';

export type { RequestStatus };

export const useEmployeeStore = defineStore('crew/employees', () => {
    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);
    const lastCreated = ref<Employee | null>(null);

    // A lista vem paginada do servidor, com busca por nome ou CPF e filtro por status.
    const list = usePagedList<Employee>('/crew/employees');

    function resetSaveState(): void {
        saveStatus.value = 'idle';
        validationErrors.value = {};
        saveError.value = null;
    }

    async function create(payload: CreateEmployeePayload): Promise<Employee | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const response = await http.post<{ data: Employee }>('/crew/employees', payload);

            lastCreated.value = response.data.data;
            saveStatus.value = 'success';
            await list.fetchPage();

            return lastCreated.value;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                saveError.value = extractErrorMessage(error, 'Não foi possível cadastrar o funcionário. Tente novamente.');
            }

            saveStatus.value = 'error';

            return null;
        }
    }

    return {
        employees: list.items,
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
        lastCreated,
        resetSaveState,
        create,
    };
});
