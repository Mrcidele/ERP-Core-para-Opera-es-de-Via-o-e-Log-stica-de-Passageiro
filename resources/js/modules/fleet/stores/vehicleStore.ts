import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { CreateVehiclePayload } from '../schemas/createVehicleSchema';
import type { Vehicle } from '../types/vehicle';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

export const useVehicleStore = defineStore('fleet/vehicles', () => {
    const status = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const errorMessage = ref<string | null>(null);
    const lastCreated = ref<Vehicle | null>(null);

    async function create(payload: CreateVehiclePayload): Promise<Vehicle | null> {
        status.value = 'loading';
        validationErrors.value = {};
        errorMessage.value = null;

        try {
            const response = await http.post<{ data: Vehicle }>('/vehicles', payload);

            lastCreated.value = response.data.data;
            status.value = 'success';

            return lastCreated.value;
        } catch (error) {
            const errors = extractValidationErrors(error);

            if (errors) {
                validationErrors.value = errors;
            } else {
                errorMessage.value = 'Não foi possível cadastrar o veículo. Tente novamente.';
            }

            status.value = 'error';

            return null;
        }
    }

    return { status, validationErrors, errorMessage, lastCreated, create };
});
