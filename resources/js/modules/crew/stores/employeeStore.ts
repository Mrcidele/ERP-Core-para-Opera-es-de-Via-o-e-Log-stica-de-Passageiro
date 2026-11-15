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

    const employees = ref<Employee[]>([]);
    const listStatus = ref<RequestStatus>('idle');

    async function fetchAll(): Promise<void> {
        listStatus.value = 'loading';

        try {
            const response = await http.get<{ data: Employee[] }>('/crew/employees');

            employees.value = response.data.data;
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

    async function create(payload: CreateEmployeePayload): Promise<Employee | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const response = await http.post<{ data: Employee }>('/crew/employees', payload);

            lastCreated.value = response.data.data;
            // A lista é ordenada por nome, como a API devolve.
            employees.value = [...employees.value, response.data.data].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
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

    return { employees, listStatus, fetchAll, saveStatus, validationErrors, saveError, lastCreated, resetSaveState, create };
});
