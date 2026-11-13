import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { CreateEmployeePayload } from '../schemas/createEmployeeSchema';
import type { Employee } from '../types/employee';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const useEmployeeStore = defineStore('crew/employees', () => {
    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);
    const lastCreated = ref<Employee | null>(null);

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

    return { saveStatus, validationErrors, saveError, lastCreated, resetSaveState, create };
});
