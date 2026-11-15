import { defineStore } from 'pinia';
import { ref } from 'vue';
import { extractErrorMessage, extractValidationErrors, http, type ValidationErrors } from '@/core/api/http';
import type { CreateVehiclePayload, UpdateVehiclePayload } from '../schemas/createVehicleSchema';
import type { Vehicle } from '../types/vehicle';

export type RequestStatus = 'idle' | 'loading' | 'success' | 'error';

const byPlate = (a: Vehicle, b: Vehicle): number => a.plate.localeCompare(b.plate);

export const useVehicleStore = defineStore('fleet/vehicles', () => {
    const vehicles = ref<Vehicle[]>([]);
    const listStatus = ref<RequestStatus>('idle');

    const saveStatus = ref<RequestStatus>('idle');
    const validationErrors = ref<ValidationErrors>({});
    const saveError = ref<string | null>(null);

    const removeStatus = ref<RequestStatus>('idle');
    const removeError = ref<string | null>(null);

    async function fetchAll(): Promise<void> {
        listStatus.value = 'loading';

        try {
            const response = await http.get<{ data: Vehicle[] }>('/fleet/vehicles');

            vehicles.value = response.data.data;
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

    async function save(request: () => Promise<{ data: { data: Vehicle } }>, failure: string): Promise<Vehicle | null> {
        resetSaveState();
        saveStatus.value = 'loading';

        try {
            const vehicle = (await request()).data.data;

            vehicles.value = [...vehicles.value.filter((item) => item.id !== vehicle.id), vehicle].sort(byPlate);
            saveStatus.value = 'success';

            return vehicle;
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

    const create = (payload: CreateVehiclePayload): Promise<Vehicle | null> =>
        save(() => http.post('/fleet/vehicles', payload), 'Não foi possível cadastrar o veículo. Tente novamente.');

    const update = (id: string, payload: UpdateVehiclePayload): Promise<Vehicle | null> =>
        save(() => http.put(`/fleet/vehicles/${id}`, payload), 'Não foi possível salvar as alterações. Tente novamente.');

    async function remove(id: string): Promise<boolean> {
        removeStatus.value = 'loading';
        removeError.value = null;

        try {
            await http.delete(`/fleet/vehicles/${id}`);

            vehicles.value = vehicles.value.filter((item) => item.id !== id);
            removeStatus.value = 'success';

            return true;
        } catch (error) {
            removeError.value = extractErrorMessage(error, 'Não foi possível excluir o veículo. Tente novamente.');
            removeStatus.value = 'error';

            return false;
        }
    }

    return {
        vehicles,
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
        remove,
    };
});
